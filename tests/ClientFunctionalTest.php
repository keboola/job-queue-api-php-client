<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\Tests;

use Keboola\JobQueueClient\Client;
use Keboola\JobQueueClient\Exception\ClientException;
use Psr\Log\NullLogger;

class ClientFunctionalTest extends BaseTest
{
    private function getClient(): Client
    {
        return new Client(
            new NullLogger(),
            (string) getenv('public_queue_api_url'),
            (string) getenv('test_storage_api_token')
        );
    }

    public function testCreateJob(): void
    {
        $client = $this->getClient();
        $response = $client->createJob([
            'component' => 'keboola.ex-db-snowflake',
            'config' => '123',
        ]);

        self::assertNotEmpty($response['id']);
        self::assertEquals('created', $response['status']);
    }

    public function testCreateInvalidJob(): void
    {
        $client = $this->getClient();
        self::expectException(ClientException::class);
        self::expectExceptionMessage('Unrecognized option \"foo\" under \"job\". Available options are \"component\",');
        $response = $client->createJob(['foo' => 'bar']);

        self::assertNotEmpty($response['id']);
        self::assertEquals('created', $response['status']);
    }
}
