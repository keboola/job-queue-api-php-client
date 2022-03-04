<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Keboola\JobQueueClient\Client;
use Keboola\JobQueueClient\Exception\ClientException;
use Keboola\JobQueueClient\Exception\ResponseException;
use Keboola\JobQueueClient\JobData;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\Log\Test\TestLogger;

class ClientTest extends BaseTest
{
    private function getClient(array $options, ?LoggerInterface $logger = null): Client
    {
        return new Client(
            $logger ?? new NullLogger(),
            'http://example.com/',
            'testToken',
            $options
        );
    }

    public function testCreateClientInvalidBackoff(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "abc" is invalid: This value should be a valid number'
        );
        new Client(
            new NullLogger(),
            'http://example.com/',
            'testToken',
            ['backoffMaxTries' => 'abc']
        );
    }

    public function testCreateClientTooLowBackoff(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "-1" is invalid: This value should be between 0 and 100.'
        );
        new Client(
            new NullLogger(),
            'http://example.com/',
            'testToken',
            ['backoffMaxTries' => -1]
        );
    }

    public function testCreateClientTooHighBackoff(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "101" is invalid: This value should be between 0 and 100.'
        );
        new Client(
            new NullLogger(),
            'http://example.com/',
            'testToken',
            ['backoffMaxTries' => 101]
        );
    }

    public function testCreateClientInvalidToken(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "" is invalid: This value should not be blank.'
        );
        new Client(new NullLogger(), 'http://example.com/', '');
    }

    public function testCreateClientInvalidUrl(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "invalid url" is invalid: This value is not a valid URL.'
        );
        new Client(new NullLogger(), 'invalid url', 'testToken');
    }

    public function testCreateClientMultipleErrors(): void
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage(
            'Invalid parameters when creating client: Value "invalid url" is invalid: This value is not a valid URL.'
            . "\n" . 'Value "" is invalid: This value should not be blank.' . "\n"
        );
        new Client(new NullLogger(), 'invalid url', '');
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
                    "status": "created",
                    "desiredStatus": "processing",
                    "mode": "run",
                    "component": "keboola.ex-db-snowflake",
                    "config": "123",
                    "configRow": null,
                    "tag": null,
                    "createdTime": "2021-03-04T21:59:49+00:00"
                }'
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($mock);
        $stack->push($history);
        $client = $this->getClient(['handler' => $stack]);
        $job = $client->createJob(new JobData('keboola.ex-db-storage', '123'));
        self::assertEquals('683194249', $job['id']);
        self::assertEquals('683194249', $job['runId']);
        self::assertEquals('created', $job['status']);
        self::assertCount(1, $requestHistory);
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
                'invalid json'
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($mock);
        $stack->push($history);
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
                'Error on server'
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
                '{not a valid json]'
            ),
        ]);

        $client = $this->getClient(['handler' => $requestHandler]);

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Unable to parse response body into JSON: ');
        $client->createJob(new JobData('keboola.ex-db-storage', '123'));
    }

    public function testRequestExceptionIsThrownForValidErrorResponse(): void
    {
        $requestHandler = MockHandler::createWithMiddleware([
            new Response(
                400,
                ['Content-Type' => 'application/json'],
                (string) json_encode([])
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
                ])
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
                    "status": "created",
                    "desiredStatus": "processing",
                    "mode": "run",
                    "component": "keboola.ex-db-snowflake",
                    "config": "123",
                    "configRow": null,
                    "tag": null,
                    "createdTime": "2021-03-04T21:59:49+00:00"
                }'
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($mock);
        $stack->push($history);
        $logger = new TestLogger();
        $client = $this->getClient(['handler' => $stack, 'logger' => $logger, 'userAgent' => 'test agent']);
        $client->createJob(new JobData('keboola.ex-db-storage', '123'));
        /** @var Request $request */
        $request = $requestHistory[0]['request'];
        self::assertEquals('test agent', $request->getHeader('User-Agent')[0]);
        self::assertTrue($logger->hasInfoThatContains('"POST  /1.1" 200 '));
        self::assertTrue($logger->hasInfoThatContains('test agent'));
    }

    public function testRetrySuccess(): void
    {
        $mock = new MockHandler([
            new Response(
                500,
                ['Content-Type' => 'application/json'],
                '{"message" => "Out of order"}'
            ),
            new Response(
                501,
                ['Content-Type' => 'application/json'],
                'Out of order'
            ),
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                '{
                    "id": "683194249",
                    "runId": "683194249",
                    "status": "created",
                    "desiredStatus": "processing",
                    "mode": "run",
                    "component": "keboola.ex-db-snowflake",
                    "config": "123",
                    "configRow": null,
                    "tag": null,
                    "createdTime": "2021-03-04T21:59:49+00:00"
                }'
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($mock);
        $stack->push($history);
        $client = $this->getClient(['handler' => $stack]);
        $job = $client->createJob(new JobData('keboola.ex-db-storage', '123'));
        self::assertEquals('683194249', $job['id']);
        self::assertEquals('683194249', $job['runId']);
        self::assertEquals('created', $job['status']);
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
                '{"message" => "Out of order"}'
            );
        }
        $mock = new MockHandler($responses);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($mock);
        $stack->push($history);
        $client = $this->getClient(['handler' => $stack, 'backoffMaxTries' => 1]);
        try {
            $client->createJob(new JobData('keboola.ex-db-storage', '123'));
            self::fail('Must throw exception');
        } catch (ClientException $e) {
            self::assertStringContainsString('500 Internal Server Error', $e->getMessage());
        }
        self::assertCount(2, $requestHistory);
    }

    public function testRetryFailureReducedBackoff(): void
    {
        $responses = [];
        for ($i = 0; $i < 30; $i++) {
            $responses[] = new Response(
                500,
                ['Content-Type' => 'application/json'],
                '{"message" => "Out of order"}'
            );
        }
        $mock = new MockHandler($responses);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($mock);
        $stack->push($history);
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
                '{"message": "Unauthorized"}'
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($mock);
        $stack->push($history);
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
                }'
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($mock);
        $stack->push($history);
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
                self::JOB_LINEAGE_RESPONSE
            ),
        ]);
        // Add the history middleware to the handler stack.
        $requestHistory = [];
        $history = Middleware::history($requestHistory);
        $stack = HandlerStack::create($mock);
        $stack->push($history);
        $client = $this->getClient(['handler' => $stack]);
        $jobLineageResponse = $client->getJobLineage('123');
        self::assertSame(self::JOB_LINEAGE_RESPONSE, $jobLineageResponse);
        self::assertCount(1, $requestHistory);
        /** @var Request $request */
        $request = $requestHistory[0]['request'];
        self::assertEquals('http://example.com/jobs/123/open-api-lineage', $request->getUri()->__toString());
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
}
