<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\Tests;

use DateTime;
use Generator;
use Keboola\JobQueueClient\Client;
use Keboola\JobQueueClient\Exception\ClientException;
use Keboola\JobQueueClient\JobData;
use Keboola\JobQueueClient\ListJobsOptions;
use Keboola\StorageApi\Client as StorageClient;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ListComponentConfigurationsOptions;

class ClientFunctionalTest extends BaseTest
{
    private const COMPONENT_ID = 'keboola.ex-db-snowflake';
    private const COMPONENT_ID_2 = 'keboola.ex-db-mysql';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::deleteConfigurations();
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

    private static function deleteConfigurations(): void
    {
        $components = new Components(self::getStorageClient());
        foreach ([self::COMPONENT_ID, self::COMPONENT_ID_2] as $componentId) {
            $configurations = $components->listComponentConfigurations(
                (new ListComponentConfigurationsOptions())
                    ->setComponentId($componentId)
                    ->setIsDeleted(false)
            );
            foreach ($configurations as $configuration) {
                $components->deleteConfiguration($componentId, $configuration['id']);
            }
        }
    }

    public function testCreateJob(): void
    {
        $configurationId = self::createConfiguration(
            self::COMPONENT_ID,
            'public-api-test'
        )['id'];

        $client = $this->getClient();
        $response = $client->createJob(new JobData(
            'keboola.ex-db-snowflake',
            $configurationId,
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

    /** @dataProvider listJobsFilterProvider() */
    public function testListJobsFilter(
        array $setValues,
        string $expectedKey,
        array $expectedValues
    ): void {
        $listOptions = new ListJobsOptions();
        foreach ($setValues as $method => $values) {
            $listOptions->$method($values);
        }
        $response = $this->getClient()->listJobs($listOptions);

        self::assertNotEmpty($response);
        self::assertEquals($expectedValues, array_column($response, $expectedKey));
    }

    public function listJobsFilterProvider(): Generator
    {
        $configurationId = self::createConfiguration(
            self::COMPONENT_ID,
            'public-api-test'
        )['id'];
        $configurationId2 = self::createConfiguration(
            self::COMPONENT_ID_2,
            'public-api-test-2'
        )['id'];
        $configurationId3 = self::createConfiguration(
            self::COMPONENT_ID_2,
            'public-api-test-3'
        )['id'];

        $client = $this->getClient();
        $client->createJob(new JobData(
            'keboola.ex-db-snowflake',
            $configurationId
        ));
        $client->createJob(new JobData(
            'keboola.ex-db-snowflake',
            $configurationId
        ));
        $client->createJob(new JobData(
            'keboola.ex-db-mysql',
            $configurationId2
        ));
        $client->createJob(new JobData(
            'keboola.ex-db-mysql',
            $configurationId3
        ));
        $client->createJob(new JobData(
            'keboola.ex-db-mysql',
            '',
            []
        ));

        $projectId = (string) $this->getStorageClient()->verifyToken()['owner']['id'];

        yield 'By configs' => [
            'setValues' => [
                'setConfigs' => [
                    $configurationId,
                    $configurationId2,
                ],
            ],
            'expectedKey' => 'config',
            'expectedValues' => [
                $configurationId2,
                $configurationId,
                $configurationId,
            ],
        ];
        yield 'By components' => [
            'setValues' => [
                'setComponents' => [self::COMPONENT_ID],
                'setConfigs' => [$configurationId],
            ],
            'expectedKey' => 'component',
            'expectedValues' => [
                self::COMPONENT_ID,
                self::COMPONENT_ID,
            ],
        ];
        yield 'By project' => [
            'setValues' => [
                'setConfigs' => [$configurationId],
                'setProjects' => [$projectId],
            ],
            'expectedKey' => 'project',
            'expectedValues' => [
                [
                    'id' => $projectId,
                    'name' => $projectId,
                ],
                [
                    'id' => $projectId,
                    'name' => $projectId,
                ],
            ],
        ];
        yield 'By statuses' => [
            'setValues' => [
                'setConfigs' => [$configurationId],
                'setStatuses' => ['created'],
            ],
            'expectedKey' => 'status',
            'expectedValue' => [
                'created',
                'created',
            ],
        ];
        yield 'By startTime' => [
            'setValues' => [
                'setConfigs' => [$configurationId],
                'setCreatedTimeFrom' => new DateTime('-1 hour'),
                'setCreatedTimeTo' => new DateTime('now'),
            ],
            'expectedKey' => 'config',
            'expectedValues' => [
                $configurationId,
                $configurationId,
            ],
        ];
    }
}
