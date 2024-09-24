<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\Tests;

use DateTimeImmutable;
use Generator;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Keboola\JobQueueClient\Client;
use Keboola\JobQueueClient\DTO\JobStates;
use Keboola\JobQueueClient\Exception\ClientException;
use Keboola\JobQueueClient\Exception\ResponseException;
use Keboola\JobQueueClient\JobData;
use Keboola\JobQueueClient\JobType;
use Keboola\JobQueueClient\ListJobsOptions;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use stdClass;

class ClientTest extends TestCase
{
    private function getClient(array $options): Client
    {
        return new Client(
            'http://example.com/',
            'testToken',
            $options,
        );
    }

    public function testCreateClientInvalidBackoff(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "abc" is invalid: This value should be a valid number',
        );
        new Client(
            'http://example.com/',
            'testToken',
            // @phpstan-ignore-next-line
            ['backoffMaxTries' => 'abc'],
        );
    }

    public function testCreateClientTooLowBackoff(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "-1" is invalid: This value should be between 0 and 100.',
        );
        new Client(
            'http://example.com/',
            'testToken',
            ['backoffMaxTries' => -1],
        );
    }

    public function testCreateClientTooHighBackoff(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "101" is invalid: This value should be between 0 and 100.',
        );
        new Client(
            'http://example.com/',
            'testToken',
            ['backoffMaxTries' => 101],
        );
    }

    public function testCreateClientInvalidToken(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "" is invalid: This value should not be blank.',
        );
        new Client('http://example.com/', '');
    }

    public function testCreateClientInvalidUrl(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "invalid url" is invalid: This value is not a valid URL.',
        );
        new Client('invalid url', 'testToken');
    }

    public function testCreateClientMultipleErrors(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "invalid url" is invalid: This value is not a valid URL.'
            . "\n" . 'Value "" is invalid: This value should not be blank.' . "\n",
        );
        new Client('invalid url', '');
    }

    public function testClientRequestResponse(): void
    {
        $mock = new MockHandler([
            new Response(
                201,
                ['Content-Type' => 'application/json'],
                '{
                    "id": "683194249",
                    "runId": "683194249",
                    "parentRunId": "",
                    "project": {"id": "123"},
                    "token": {"id": "456", "description": "my token"},
                    "status": "created",
                    "desiredStatus": "processing",
                    "mode": "run",
                    "component": "keboola.ex-db-snowflake",
                    "config": "123",
                    "configData": {},
                    "configRowIds": null,
                    "tag": null,
                    "createdTime": "2021-03-04T21:59:49+00:00",
                    "startTime": null,
                    "endTime": null,
                    "durationSeconds": 0,
                    "result": [],
                    "usageData": [],
                    "isFinished": false,
                    "url": "https://queue.east-us-2.azure.keboola-testing.com/jobs/683194249",
                    "branchId": "6",
                    "variableValuesId": null,
                    "variableValuesData": {"values": []},
                    "backend": {"context": "18-transformation"},
                    "executor": "dind",
                    "metrics": [],
                    "behavior": {"onError": null},
                    "parallelism": null,
                    "type": "standard",
                    "orchestrationJobId": null,
                    "orchestrationTaskId": null,
                    "onlyOrchestrationTaskIds": null,
                    "previousJobId": null
                }',
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($history($mock));

        $client = $this->getClient(['handler' => $stack]);
        $job = $client->createJob(new JobData('keboola.ex-db-storage', '123'));
        self::assertEquals('683194249', $job->id);
        self::assertEquals('683194249', $job->runId);
        self::assertEquals('created', $job->status);
        self::assertCount(1, $requestHistory);
        self::assertFalse($job->isError());
        self::assertFalse($job->isSuccess());

        /** @var Request $request */
        $request = $requestHistory[0]['request'];
        self::assertEquals('http://example.com/jobs', $request->getUri()->__toString());
        self::assertEquals('POST', $request->getMethod());
        self::assertEquals('testToken', $request->getHeader('X-StorageApi-Token')[0]);
        self::assertEquals('Job Queue PHP Client', $request->getHeader('User-Agent')[0]);
        self::assertEquals('application/json', $request->getHeader('Content-type')[0]);
    }

    public function testInvalidResponse(): void
    {
        $mock = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                'invalid json',
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($history($mock));

        $client = $this->getClient(['handler' => $stack]);
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid job data: Type is not supported');
        $res = fopen(sys_get_temp_dir() . '/touch', 'w');
        $client->createJob(new JobData('keboola.ex-db-storage', '123', ['foo' => $res]));
    }

    public function testClientExceptionIsThrownWhenGuzzleRequestErrorOccurs(): void
    {
        $requestHandler = MockHandler::createWithMiddleware([
            new Response(
                500,
                ['Content-Type' => 'text/plain'],
                'Error on server',
            ),
        ]);

        $client = $this->getClient(['handler' => $requestHandler, 'backoffMaxTries' => 0]);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Error on server');
        $client->createJob(new JobData('keboola.ex-db-storage', '123'));
    }

    public function testClientExceptionIsThrownForResponseWithInvalidJson(): void
    {
        $requestHandler = MockHandler::createWithMiddleware([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                '{not a valid json]',
            ),
        ]);

        $client = $this->getClient(['handler' => $requestHandler]);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Unable to parse response body into JSON: Syntax error');
        $this->expectExceptionCode(0);
        $client->createJob(new JobData('keboola.ex-db-storage', '123'));
    }

    public function testRequestExceptionIsThrownForValidErrorResponse(): void
    {
        $requestHandler = MockHandler::createWithMiddleware([
            new Response(
                400,
                ['Content-Type' => 'application/json'],
                (string) json_encode([]),
            ),
        ]);

        $client = $this->getClient(['handler' => $requestHandler]);

        $this->expectException(ResponseException::class);
        $this->expectExceptionMessage('400 Bad Request');
        $client->createJob(new JobData('keboola.ex-db-storage', '123'));
    }

    public function testRequestExceptionIsThrownForErrorResponseWithErrorCode(): void
    {
        $requestHandler = MockHandler::createWithMiddleware([
            new Response(
                400,
                ['Content-Type' => 'application/json'],
                (string) json_encode([
                    'context' => [
                        'errorCode' => 'some.error',
                    ],
                ]),
            ),
        ]);

        $client = $this->getClient(['handler' => $requestHandler]);

        try {
            $client->createJob(new JobData('keboola.ex-db-storage', '123'));
        } catch (ResponseException $e) {
            self::assertTrue($e->isErrorCode('some.error'));
        }
    }

    public function testLogger(): void
    {
        $mock = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                '{
                    "id": "683194249",
                    "runId": "683194249",
                    "parentRunId": "",
                    "project": {"id": "123"},
                    "token": {"id": "456", "description": "my token"},
                    "status": "created",
                    "status": "created",
                    "desiredStatus": "processing",
                    "mode": "run",
                    "component": "keboola.ex-db-snowflake",
                    "config": "123",
                    "configData": {},
                    "configRowIds": null,
                    "tag": null,
                    "createdTime": "2021-03-04T21:59:49+00:00",
                    "startTime": null,
                    "endTime": null,
                    "durationSeconds": 0,
                    "result": [],
                    "usageData": [],
                    "isFinished": false,
                    "url": "https://queue.east-us-2.azure.keboola-testing.com/jobs/683194249",
                    "branchId": "6",
                    "variableValuesId": null,
                    "variableValuesData": {"values": []},
                    "backend": {"context": "18-transformation"},
                    "executor": "dind",
                    "metrics": [],
                    "behavior": {"onError": null},
                    "parallelism": null,
                    "type": "standard",
                    "orchestrationJobId": null,
                    "orchestrationTaskId": null,
                    "onlyOrchestrationTaskIds": null,
                    "previousJobId": null
                }',
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($history($mock));

        $handler = new TestHandler();
        $logger = new Logger('testLogger', [$handler]);
        $client = $this->getClient(['handler' => $stack, 'logger' => $logger, 'userAgent' => 'test agent']);
        $client->createJob(new JobData('keboola.ex-db-storage', '123'));
        /** @var Request $request */
        $request = $requestHistory[0]['request'];
        self::assertEquals('test agent', $request->getHeader('User-Agent')[0]);
        self::assertTrue($handler->hasInfoThatContains('"POST  /1.1" 200 '));
        self::assertTrue($handler->hasInfoThatContains('test agent'));
    }

    public function testRetrySuccess(): void
    {
        $mock = new MockHandler([
            new Response(
                500,
                ['Content-Type' => 'application/json'],
                '{"message" => "Out of order"}',
            ),
            new Response(
                501,
                ['Content-Type' => 'application/json'],
                'Out of order',
            ),
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                '{
                    "id": "683194249",
                    "runId": "683194249",
                    "parentRunId": "",
                    "project": {"id": "123"},
                    "token": {"id": "456", "description": "my token"},
                    "status": "created",
                    "desiredStatus": "processing",
                    "mode": "run",
                    "component": "keboola.ex-db-snowflake",
                    "config": "123",
                                        "configData": {},
                    "configRowIds": null,
                    "tag": null,
                    "createdTime": "2021-03-04T21:59:49+00:00",
                    "startTime": null,
                    "endTime": null,
                    "durationSeconds": 0,
                    "result": [],
                    "usageData": [],
                    "isFinished": false,
                    "url": "https://queue.east-us-2.azure.keboola-testing.com/jobs/683194249",
                    "branchId": "6",
                    "variableValuesId": null,
                    "variableValuesData": {"values": []},
                    "backend": {"context": "18-transformation"},
                    "executor": "dind",
                    "metrics": [],
                    "behavior": {"onError": null},
                    "parallelism": null,
                    "type": "standard",
                    "orchestrationJobId": null,
                    "orchestrationTaskId": null,
                    "onlyOrchestrationTaskIds": null,
                    "previousJobId": null
                }',
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($history($mock));

        $client = $this->getClient(['handler' => $stack]);
        $job = $client->createJob(new JobData('keboola.ex-db-storage', '123'));
        self::assertEquals('683194249', $job->id);
        self::assertEquals('683194249', $job->runId);
        self::assertEquals('created', $job->status);
        self::assertCount(3, $requestHistory);
        /** @var Request $request */
        $request = $requestHistory[0]['request'];
        self::assertEquals('http://example.com/jobs', $request->getUri()->__toString());
        $request = $requestHistory[1]['request'];
        self::assertEquals('http://example.com/jobs', $request->getUri()->__toString());
        $request = $requestHistory[2]['request'];
        self::assertEquals('http://example.com/jobs', $request->getUri()->__toString());
    }

    public function testRetryFailure(): void
    {
        $responses = [];
        for ($i = 0; $i < 30; $i++) {
            $responses[] = new Response(
                500,
                ['Content-Type' => 'application/json'],
                '{"message" => "Out of order"}',
            );
        }
        $mock = new MockHandler($responses);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($history($mock));

        $client = $this->getClient(['handler' => $stack]);
        try {
            $client->createJob(new JobData('keboola.ex-db-storage', '123'));
            self::fail('Must throw exception');
        } catch (ClientException $e) {
            self::assertStringContainsString('500 Internal Server Error', $e->getMessage());
        }
        self::assertCount(4, $requestHistory);
    }

    public function testRetryFailureReducedBackoff(): void
    {
        $responses = [];
        for ($i = 0; $i < 30; $i++) {
            $responses[] = new Response(
                500,
                ['Content-Type' => 'application/json'],
                '{"message" => "Out of order"}',
            );
        }
        $mock = new MockHandler($responses);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($history($mock));

        $client = $this->getClient(['handler' => $stack, 'backoffMaxTries' => 3]);
        try {
            $client->createJob(new JobData('keboola.ex-db-storage', '123'));
            self::fail('Must throw exception');
        } catch (ClientException $e) {
            self::assertStringContainsString('500 Internal Server Error', $e->getMessage());
        }
        self::assertCount(4, $requestHistory);
    }

    public function testNoRetry(): void
    {
        $mock = new MockHandler([
            new Response(
                401,
                ['Content-Type' => 'application/json'],
                '{"message": "Unauthorized"}',
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($history($mock));

        $client = $this->getClient(['handler' => $stack]);
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('{"message": "Unauthorized"}');
        $client->createJob(new JobData('keboola.ex-db-storage', '123'));
    }

    public function testGetJobsDurationSum(): void
    {
        $mock = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                '{
                    "jobs": {
                        "durationSum": 456
                    }
                }',
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($history($mock));

        $client = $this->getClient(['handler' => $stack]);
        $durationSum = $client->getJobsDurationSum();
        self::assertSame(456, $durationSum);

        self::assertCount(1, $requestHistory);
        /** @var Request $request */
        $request = $requestHistory[0]['request'];
        self::assertEquals('http://example.com/stats/project', $request->getUri()->__toString());
        self::assertEquals('GET', $request->getMethod());
        self::assertEquals('testToken', $request->getHeader('X-StorageApi-Token')[0]);
        self::assertEquals('Job Queue PHP Client', $request->getHeader('User-Agent')[0]);
        self::assertEquals('application/json', $request->getHeader('Content-type')[0]);
    }

    public function testGetJobLineage(): void
    {
        $mock = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                self::JOB_LINEAGE_RESPONSE,
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($history($mock));

        $client = $this->getClient(['handler' => $stack]);
        $jobLineageResponse = $client->getJobLineage('123');
        self::assertSame(json_decode(self::JOB_LINEAGE_RESPONSE, true), $jobLineageResponse);
        self::assertCount(1, $requestHistory);
        /** @var Request $request */
        $request = $requestHistory[0]['request'];
        self::assertEquals('http://example.com/job/123/open-api-lineage', $request->getUri()->__toString());
        self::assertEquals('GET', $request->getMethod());
        self::assertEquals('testToken', $request->getHeader('X-StorageApi-Token')[0]);
        self::assertEquals('Job Queue PHP Client', $request->getHeader('User-Agent')[0]);
        self::assertEquals('application/json', $request->getHeader('Content-type')[0]);
    }

    private const JOB_LINEAGE_RESPONSE = '[
        {
            "eventType": "START",
            "eventTime": "2022-03-04T12:07:00.406Z",
            "run": {
              "runId": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
              "facets": {
                "parent": {
                  "_producer": "https://connection.north-europe.azure.keboola.com",
                  "_schemaURL": "https://openlineage.io/spec/facets/1-0-0/ParentRunFacet.json#/$defs/ParentRunFacet",
                  "run": {
                    "runId": "3fa85f64-5717-4562-b3fc-2c963f66afa6"
                  },
                  "job": {
                    "namespace": "connection.north-europe.azure.keboola.com/project/1234",
                    "name": "keboola.orchestrator-123"
                  }
                }
              }
            },
            "job": {
              "namespace": "connection.north-europe.azure.keboola.com/project/1234",
              "name": "keboola.snowflake-transformation-123456"
            },
            "producer": "https://connection.north-europe.azure.keboola.com",
            "inputs": [
              {
                "namespace": "connection.north-europe.azure.keboola.com/project/1234",
                "name": "in.c-kds-team-ex-shoptet-permalink-1234567.orders",
                "facets": {
                  "schema": {
                    "_producer": "https://connection.north-europe.azure.keboola.com",
                    "_schemaURL": "https://openlineage.io/spec/1-0-2/OpenLineage.json#/$defs/InputDatasetFacet",
                    "fields": [
                      {
                        "name": "code"
                      },
                      {
                        "name": "date"
                      },
                      {
                        "name": "totalPriceWithVat"
                      },
                      {
                        "name": "currency"
                      }
                    ]
                  }
                }
              }
            ]
            },
            {
            "eventType": "COMPLETE",
            "eventTime": "2022-03-04T12:07:00.406Z",
            "run": {
              "runId": "3fa85f64-5717-4562-b3fc-2c963f66afa6"
            },
            "job": {
              "namespace": "connection.north-europe.azure.keboola.com/project/1234",
              "name": "keboola.snowflake-transformation-123456"
            },
            "producer": "https://connection.north-europe.azure.keboola.com",
            "outputs": [
              {
                "namespace": "connection.north-europe.azure.keboola.com/project/1234",
                "name": "out.c-orders.dailyStats\"",
                "facets": {
                  "schema": {
                    "_producer": "https://connection.north-europe.azure.keboola.com",
                    "_schemaURL": "https://openlineage.io/spec/1-0-2/OpenLineage.json#/$defs/OutputDatasetFacet",
                    "fields": [
                      {
                        "name": "date"
                      },
                      {
                        "name": "ordersCount"
                      },
                      {
                        "name": "totalPriceEuroSum"
                      }
                    ]
                  }
                }
              }
            ]
            }
        ]';

    /** @dataProvider provideListJobsOptionsTestData */
    public function testListJobsOptions(ListJobsOptions $jobListOptions, string $expectedRequestUri): void
    {
        $mock = new MockHandler([
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                (string) json_encode([
                    [
                        'id' => '123',
                        'runId' => '123',
                        'parentRunId' => '',
                        'branchId' => 'dev-branch',
                        'configRowIds' => [],
                        'tag' => '1.2.3',
                        'project' => [
                            'id' => '456',
                            'name' => 'Test project',
                        ],
                        'token' => [
                            'id' => '789',
                            'description' => 'my token',
                        ],
                        'status' => 'created',
                        'desiredStatus' => 'processing',
                        'mode' => 'run',
                        'component' => 'keboola.test',
                        'config' => '123456',
                        'configData' => [
                            'parameters' => [
                                'foo' => 'bar',
                            ],
                        ],
                        'createdTime' => '2022-03-01T13:17:05+10:00',
                        'startTime' => '2022-03-01T13:17:06+10:00',
                        'endTime' => '2022-03-01T13:18:06+10:00',
                        'durationSeconds' => 3600,
                        'result' => new stdClass(),
                        'usageData' => new stdClass(),
                        'isFinished' => false,
                        'url' => 'http://example.com/jobs/123',
                        'variableValuesId' => null,
                        'variableValuesData' => [
                            'values' => [],
                        ],
                        'backend' => [],
                        'executor' => 'dind',
                        'metrics' => [
                            'backend' => [
                                'context' => null,
                                'type' => 'small',
                            ],
                            'storage' => [
                                'inputTablesBytesSum' => 0,
                            ],
                        ],
                        'behavior' => [
                            'onError' => null,
                        ],
                        'parallelism' => null,
                        'type' => 'standard',
                        'orchestrationJobId' => null,
                        'orchestrationTaskId' => null,
                        'onlyOrchestrationTaskIds' => null,
                        'previousJobId' => null,
                    ],
                ]),
            ),
        ]);

        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($history($mock));

        $client = $this->getClient(['handler' => $stack]);
        $client->listJobs($jobListOptions);

        $request = $requestHistory[0]['request'];
        self::assertSame($expectedRequestUri, $request->getUri()->__toString());
    }

    public function provideListJobsOptionsTestData(): iterable
    {
        yield 'empty options' => [
            'options' => new ListJobsOptions(),
            'url' => 'http://example.com/jobs?limit=100',
        ];

        yield 'custom limit' => [
            'options' => (new ListJobsOptions())->setLimit(50),
            'url' => 'http://example.com/jobs?limit=50',
        ];

        yield 'sort by id, asc' => [
            'options' => (new ListJobsOptions())
                ->setSortBy('id')
                ->setSortOrder('asc'),
            'url' => 'http://example.com/jobs?limit=100&sortBy=id&sortOrder=asc',
        ];

        yield 'filter date range' => [
            'options' => (new ListJobsOptions())
                ->setCreatedTimeFrom(new DateTimeImmutable('2022-03-01T12:17:05+10:00'))
                ->setCreatedTimeTo(new DateTimeImmutable('2022-07-14T05:11:45-08:20')),
            'url' => 'http://example.com/jobs?limit=100'
                . '&createdTimeFrom=2022-03-01T12%3A17%3A05%2B10%3A00'
                . '&createdTimeTo=2022-07-14T05%3A11%3A45-08%3A20',
        ];

        yield 'filter by components' => [
            'options' => (new ListJobsOptions())
                ->setComponents([
                    'keboola.test',
                ]),
            'url' => 'http://example.com/jobs?component%5B0%5D=keboola.test&limit=100',
        ];

        yield 'filter by config' => [
            'options' => (new ListJobsOptions())
                ->setConfigs(['123456']),
            'url' => 'http://example.com/jobs?config%5B0%5D=123456&limit=100',
        ];

        yield 'filter by type' => [
            'options' => (new ListJobsOptions())
                ->setType(JobType::STANDARD),
            'url' => 'http://example.com/jobs?limit=100&type=standard',
        ];

        yield 'filter by branch' => [
            'options' => (new ListJobsOptions())
                ->setBranchIds(['dev-branch']),
            'url' => 'http://example.com/jobs?branchId%5B0%5D=dev-branch&limit=100',
        ];

        yield 'filter by token id' => [
            'options' => (new ListJobsOptions())
                ->setTokenIds(['789']),
            'url' => 'http://example.com/jobs?tokenId%5B0%5D=789&limit=100',
        ];
    }

    public function testRetryCurlExceptionFail(): void
    {
        $mock = new MockHandler(
            [
                new Response(500, ['Content-Type' => 'application/json'], 'not used'),
                new Response(500, ['Content-Type' => 'application/json'], 'not used'),
                new Response(500, ['Content-Type' => 'application/json'], 'not used'),
            ],
            function (ResponseInterface $a) {
                // abusing the mockhandler here: override the mock response and throw a Request exception
                throw new RequestException(
                    'API error: cURL error 56: OpenSSL SSL_read: Connection reset by peer, errno 104',
                    new Request('GET', 'https://example.com'),
                    null,
                    null,
                    [
                        'errno' => 56,
                        'error' => 'OpenSSL SSL_read: Connection reset by peer, errno 104',
                    ],
                );
            },
        );

        // Add the history middleware to the handler stack.
        $container = [];
        $history = Middleware::history($container);
        $stack = HandlerStack::create($history($mock));

        $client = $this->getClient(['handler' => $stack, 'backoffMaxTries' => 2]);
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('API error: cURL error 56: OpenSSL SSL_read: Connection reset by peer');
        $client->createJob(new JobData('dummy'));
    }

    /** @dataProvider curlErrorProvider */
    public function testRetryCurlException(int $curlErrorNumber): void
    {
        $mock = new MockHandler(
            [
                new Response(500, ['Content-Type' => 'application/json'], 'not used'),
                new Response(500, ['Content-Type' => 'application/json'], 'not used'),
                new Response(
                    200,
                    ['Content-Type' => 'application/json'],
                    '{
                        "id": "683194249",
                        "runId": "683194249",
                        "parentRunId": "",
                        "project": {"id": "123"},
                        "token": {"id": "456", "description": "my token"},
                        "status": "created",
                        "desiredStatus": "processing",
                        "mode": "run",
                        "component": "keboola.ex-db-snowflake",
                        "config": "123",
                        "configData": {},
                        "configRowIds": null,
                        "tag": null,
                        "createdTime": "2021-03-04T21:59:49+00:00",
                        "startTime": null,
                        "endTime": null,
                        "durationSeconds": 0,
                        "result": [],
                        "usageData": [],
                        "isFinished": false,
                        "url": "https://queue.east-us-2.azure.keboola-testing.com/jobs/683194249",
                        "branchId": "6",
                        "variableValuesId": null,
                        "variableValuesData": {"values": []},
                        "backend": {"context": "18-transformation"},
                        "executor": "dind",
                        "metrics": [],
                        "behavior": {"onError": null},
                        "parallelism": null,
                        "type": "standard",
                        "orchestrationJobId": null,
                        "orchestrationTaskId": null,
                        "onlyOrchestrationTaskIds": null,
                        "previousJobId": null
                    }',
                ),
            ],
            function (ResponseInterface $a) use ($curlErrorNumber) {
                if ($a->getStatusCode() === 500) {
                    // abusing the mockhandler here: override the mock response and throw a Request exception
                    throw new RequestException(
                        'API error: cURL error 56: OpenSSL SSL_read: Connection reset by peer, errno 104',
                        new Request('GET', 'https://example.com'),
                        null,
                        null,
                        [
                            'errno' => $curlErrorNumber,
                            'error' => 'OpenSSL SSL_read: Connection reset by peer, errno 104',
                        ],
                    );
                }
            },
        );

        // Add the history middleware to the handler stack.
        $container = [];
        $history = Middleware::history($container);
        $stack = HandlerStack::create($history($mock));

        $client = $this->getClient(['handler' => $stack, 'backoffMaxTries' => 2]);
        $result = $client->createJob(new JobData('dummy'));
        self::assertSame('683194249', $result->id);
    }

    public function curlErrorProvider(): array
    {
        return [
            [56],
            [55],
        ];
    }

    public function testRetryCurlExceptionWithoutContext(): void
    {
        $mock = new MockHandler(
            [
                new Response(500, ['Content-Type' => 'application/json'], 'not used'),
            ],
            function (ResponseInterface $a) {
                // abusing the mockhandler here: override the mock response and throw a Request exception
                throw new RequestException(
                    'API error: cURL error 56: OpenSSL SSL_read: Connection reset by peer, errno 104',
                    new Request('GET', 'https://example.com'),
                    null,
                    null,
                    [],
                );
            },
        );

        // Add the history middleware to the handler stack.
        $container = [];
        $history = Middleware::history($container);
        $stack = HandlerStack::create($history($mock));

        $client = $this->getClient(['handler' => $stack, 'backoffMaxTries' => 2]);
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('API error: cURL error 56: OpenSSL SSL_read: Connection reset by peer');
        $client->createJob(new JobData('dummy'));
    }

    public function testWaitForCompletion(): void
    {
        $body = '{
            "id": "683194249",
            "runId": "683194249",
            "parentRunId": "",
            "project": {"id": "123"},
            "token": {"id": "456", "description": "my token"},
            "status": "created",
            "desiredStatus": "processing",
            "mode": "run",
            "component": "keboola.ex-db-snowflake",
            "config": "123",
            "configData": {},
            "configRowIds": null,
            "tag": null,
            "createdTime": "2021-03-04T21:59:49+00:00",
            "startTime": null,
            "endTime": null,
            "durationSeconds": 0,
            "result": [],
            "usageData": [],
            "isFinished": false,
            "url": "https://queue.east-us-2.azure.keboola-testing.com/jobs/683194249",
            "branchId": "6",
            "variableValuesId": null,
            "variableValuesData": {"values": []},
            "backend": {"context": "18-transformation"},
            "executor": "dind",
            "metrics": [],
            "behavior": {"onError": null},
            "parallelism": null,
            "type": "standard",
            "orchestrationJobId": null,
            "orchestrationTaskId": null,
            "onlyOrchestrationTaskIds": null,
            "previousJobId": null
        }';
        $finalBody = json_decode($body, true);
        $finalBody['isFinished'] = true;
        $finalBody = (string) json_encode($finalBody);
        $mock = new MockHandler([
            new Response(
                201,
                ['Content-Type' => 'application/json'],
                $body,
            ),
            new Response(
                201,
                ['Content-Type' => 'application/json'],
                $body,
            ),
            new Response(
                201,
                ['Content-Type' => 'application/json'],
                $finalBody,
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($history($mock));

        $client = $this->getClient(['handler' => $stack]);
        $job = $client->createJob(new JobData('keboola.ex-db-storage', '123'));
        $job = $client->waitForJobCompletion($job->id);

        self::assertSame('683194249', $job->id);
        self::assertSame('683194249', $job->runId);
        self::assertSame(true, $job->isFinished);
        self::assertCount(3, $requestHistory);
        /** @var Request $request */
        $request = $requestHistory[0]['request'];
        self::assertSame('http://example.com/jobs', $request->getUri()->__toString());
        self::assertSame('POST', $request->getMethod());
        self::assertSame('testToken', $request->getHeader('X-StorageApi-Token')[0]);
        self::assertSame('Job Queue PHP Client', $request->getHeader('User-Agent')[0]);
        self::assertSame('application/json', $request->getHeader('Content-type')[0]);
    }
}
