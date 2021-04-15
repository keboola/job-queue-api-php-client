<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\Tests;

use Keboola\JobQueueClient\Client;
use Keboola\JobQueueClient\Exception\ClientException;
use Keboola\JobQueueClient\JobData;
use Psr\Log\NullLogger;

class ClientFunctionalTest extends BaseTest
{
    private function getClient(array $options = []): Client
    {
        return new Client(
            new NullLogger(),
            (string) getenv('public_queue_api_url'),
            (string) getenv('test_storage_api_token'),
            $options
        );
    }

    public function testCreateJob(): void
    {
        $client = $this->getClient();
        $response = $client->createJob(new JobData(
            'keboola.ex-db-snowflake',
            '123',
        ));

        self::assertNotEmpty($response['id']);
        self::assertEquals('created', $response['status']);
    }

    public function testCreateInvalidJob(): void
    {
        $client = new Client(
            new NullLogger(),
            (string) getenv('public_queue_api_url'),
            'invalid',
        );
        self::expectException(ClientException::class);
        self::expectExceptionMessage('Invalid access token');
        $client->createJob(new JobData('foo', '123'));
    }
}
