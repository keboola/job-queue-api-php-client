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
        self::expectException(ClientException::class);
        self::expectExceptionMessage(
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
        self::expectException(ClientException::class);
        self::expectExceptionMessage(
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
        self::expectException(ClientException::class);
        self::expectExceptionMessage(
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
        self::expectException(ClientException::class);
        self::expectExceptionMessage(
            'Invalid parameters when creating client: Value "" is invalid: This value should not be blank.'
        );
        new Client(new NullLogger(), 'http://example.com/', '');
    }

    public function testCreateClientInvalidUrl(): void
    {
        self::expectException(ClientException::class);
        self::expectExceptionMessage(
            'Invalid parameters when creating client: Value "invalid url" is invalid: This value is not a valid URL.'
        );
        new Client(new NullLogger(), 'invalid url', 'testToken');
    }

    public function testCreateClientMultipleErrors(): void
    {
        self::expectException(ClientException::class);
        self::expectExceptionMessage(
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
        $job = $client->createJob(['componentId' => 'keboola.ex-db-storage', 'config' => '123']);
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
        self::expectException(ClientException::class);
        self::expectExceptionMessage('Unable to parse response body into JSON: Syntax error');
        $client->createJob(['123']);
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
        $client->createJob(['123']);
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
                500,
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
        $job = $client->createJob(['123']);
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
            $client->createJob(['123']);
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
            $client->createJob(['123']);
            self::fail('Must throw exception');
        } catch (ClientException $e) {
            self::assertStringContainsString('500 Internal Server Error', $e->getMessage());
        }
        self::assertCount(4, $requestHistory);
    }
}
