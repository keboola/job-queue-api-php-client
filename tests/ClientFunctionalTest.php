<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\Tests;

use Keboola\JobQueueClient\Client;
use Keboola\JobQueueClient\Exception\ClientException;
use Keboola\JobQueueClient\JobData;
use Keboola\JobQueueClient\ListJobsOptions;
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
        self::$configurationId = self::createConfiguration(
            self::COMPONENT_ID,
            'public-api-test'
        )['id'];
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$configurationId) {
            $client = self::getStorageClient();
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

    private static function getStorageClient(): StorageClient
    {
        return new StorageClient([
            'token' => (string) getenv('test_storage_api_token'),
            'url' => (string) getenv('storage_api_url'),
        ]);
    }

    private static function createConfiguration(string $componentId, string $name): array
    {
        $components = new Components(self::getStorageClient());
        $configuration = new Configuration();
        $configuration->setComponentId($componentId);
        $configuration->setName($name);
        $configuration->setConfiguration([]);
        return (array) $components->addConfiguration($configuration);
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

    public function testListJobsByComponent(): void
    {
        $client = $this->getClient();
        $createdJob1 = $client->createJob(new JobData(
            'keboola.ex-db-snowflake',
            '',
            [],
        ));
        $createdJob2 = $client->createJob(new JobData(
            'keboola.ex-db-snowflake',
            '',
            [],
        ));
        $client->createJob(new JobData(
            'keboola.ex-db-mysql',
            '',
            [],
        ));
        $client->createJob(new JobData(
            'keboola.ex-db-pgsql',
            '',
            [],
        ));

        $response = $client->listJobs(
            (new ListJobsOptions())
                ->setComponents([
                    'keboola.ex-db-snowflake',
                ])
        );

        self::assertNotEmpty($response);
        self::assertEquals($createdJob1['id'], $response[1]['id']);
        self::assertEquals($createdJob2['id'], $response[0]['id']);
        self::assertEquals($createdJob1['component'], $response[1]['component']);
        self::assertEquals($createdJob2['component'], $response[0]['component']);
    }

    public function testListJobsByConfig(): void
    {
        $config2 = $this->createConfiguration('keboola.ex-db-mysql', 'public-api-test-2');
        $config3 = $this->createConfiguration('keboola.ex-db-mysql', 'public-api-test-2');

        $client = $this->getClient();
        $client->createJob(new JobData(
            'keboola.ex-db-snowflake',
            self::$configurationId
        ));
        $client->createJob(new JobData(
            'keboola.ex-db-snowflake',
            self::$configurationId
        ));
        $createdJob1 = $client->createJob(new JobData(
            'keboola.ex-db-mysql',
            $config2['id']
        ));
        $createdJob2 = $client->createJob(new JobData(
            'keboola.ex-db-mysql',
            $config3['id']
        ));

        $response = $client->listJobs(
            (new ListJobsOptions())
                ->setConfigs([
                    $config2['id'],
                    $config3['id']
                ])
        );

        self::assertNotEmpty($response);
        self::assertEquals($createdJob1['id'], $response[1]['id']);
        self::assertEquals($createdJob2['id'], $response[0]['id']);
        self::assertEquals($createdJob1['config'], $response[1]['config']);
        self::assertEquals($createdJob2['config'], $response[0]['config']);
    }


}
