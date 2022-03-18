<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\Tests;

use Keboola\JobQueueClient\Client;
use Keboola\JobQueueClient\Exception\ClientException;
use Keboola\JobQueueClient\JobData;
use Keboola\StorageApi\Client as StorageClient;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Psr\Log\NullLogger;

class ClientFunctionalTest extends BaseTest
{
    private const COMPONENT_ID = 'keboola.ex-db-snowflake';
    /** @var string */
    private static $configurationId = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $client = new StorageClient([
            'token' => (string) getenv('test_storage_api_token'),
            'url' => (string) getenv('storage_api_url'),
        ]);
        $components = new Components($client);
        $configuration = new Configuration();
        $configuration->setComponentId(self::COMPONENT_ID);
        $configuration->setName('scheduler-test');
        $configuration->setConfiguration([]);
        self::$configurationId = (string) $components->addConfiguration($configuration)['id'];
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$configurationId) {
            $client = new StorageClient([
                'token' => (string) getenv('test_storage_api_token'),
                'url' => (string) getenv('storage_api_url'),
            ]);
            $components = new Components($client);
            $components->deleteConfiguration(self::COMPONENT_ID, self::$configurationId);
        }
        parent::tearDownAfterClass();
    }

    private function getClient(array $options = []): Client
    {
        return new Client(
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
            self::$configurationId,
            []
        ));

        self::assertNotEmpty($response['id']);
        self::assertEquals('created', $response['status']);
    }

    public function testCreateJobWithConfigData(): void
    {
        $client = $this->getClient();
        $response = $client->createJob(new JobData(
            'keboola.ex-db-snowflake',
            null,
            [
                'parameters' => [
                    'foo' => 'bar',
                ],
            ]
        ));

        self::assertNotEmpty($response['id']);
        self::assertEquals('created', $response['status']);
    }

    public function testCreateInvalidJob(): void
    {
        $client = new Client(
            (string) getenv('public_queue_api_url'),
            'invalid',
        );
        self::expectException(ClientException::class);
        self::expectExceptionMessage('Invalid access token');
        $client->createJob(new JobData('foo', '', []));
    }

    public function testGetJob(): void
    {
        $client = $this->getClient();
        $createJobResponse = $client->createJob(new JobData(
            'keboola.ex-db-snowflake',
            '',
            [],
        ));
        $response = $client->getJob($createJobResponse['id']);
        self::assertEquals($createJobResponse['id'], $response['id']);
        self::assertEquals('created', $response['status']);
    }
}
