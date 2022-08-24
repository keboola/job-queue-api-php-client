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
use Throwable;

class ClientFunctionalTest extends BaseTest
{
    private const COMPONENT_ID = 'keboola.ex-db-snowflake';
    private const COMPONENT_ID_2 = 'keboola.ex-db-mysql';
    private const COMPONENT_ID_3 = 'keboola.ex-db-pgsql';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $components = new Components(self::getStorageClient());
        foreach ([self::COMPONENT_ID, self::COMPONENT_ID_2, self::COMPONENT_ID_3] as $componentId) {
            $configurations = $components->listComponentConfigurations(
                (new ListComponentConfigurationsOptions())
                    ->setComponentId($componentId)
            );
            foreach ($configurations as $configuration) {
                $components->deleteConfiguration($componentId, $configuration['id']);
            }
        }
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

    private static function deleteConfiguration(string $componentId, string $configurationId): void
    {
        $components = new Components(self::getStorageClient());
        try {
            $components->deleteConfiguration($componentId, $configurationId);
        } catch (Throwable $e) {
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
            self::COMPONENT_ID,
            $configurationId,
            []
        ));

        self::assertNotEmpty($response['id']);
        self::assertEquals('created', $response['status']);

        self::deleteConfiguration(self::COMPONENT_ID, $configurationId);
    }

    public function testCreateJobWithConfigData(): void
    {
        $client = $this->getClient();
        $response = $client->createJob(new JobData(
            self::COMPONENT_ID,
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
            self::COMPONENT_ID,
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
            self::COMPONENT_ID,
            $configurationId
        ));
        $client->createJob(new JobData(
            self::COMPONENT_ID,
            $configurationId
        ));
        $client->createJob(new JobData(
            self::COMPONENT_ID_2,
            $configurationId2
        ));
        $client->createJob(new JobData(
            self::COMPONENT_ID_2,
            $configurationId3
        ));
        $client->createJob(new JobData(
            self::COMPONENT_ID_3,
            '',
            []
        ));

        $tokenRes = $this->getStorageClient()->verifyToken();
        $projectId = $tokenRes['owner']['id'];

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
        yield 'By token id' => [
            'setValues' => [
                'setConfigs' => [$configurationId],
                'setTokenIds' => [$tokenRes['id']],
            ],
            'expectedKey' => 'token',
            'expectedValue' => [
                [
                    'id' => $tokenRes['id'],
                    'description' => $tokenRes['description'],
                ],
                [
                    'id' => $tokenRes['id'],
                    'description' => $tokenRes['description'],
                ],
            ],
        ];
    }

    public function testListJobsLimit(): void
    {
        $client = $this->getClient();
        $client->createJob(new JobData(
            self::COMPONENT_ID_3,
            '',
            []
        ));
        $client->createJob(new JobData(
            self::COMPONENT_ID_3,
            '',
            []
        ));
        $client->createJob(new JobData(
            self::COMPONENT_ID_3,
            '',
            []
        ));

        $listOptions = new ListJobsOptions();
        $listOptions->setLimit(2);
        $response = $this->getClient()->listJobs($listOptions);

        self::assertNotEmpty($response);
        self::assertCount(2, $response);
    }

    public function testTerminateJob(): void
    {
        $client = $this->getClient();
        $job = $client->createJob(new JobData(
            self::COMPONENT_ID,
            '',
            []
        ));
        $client->terminateJob($job['id']);
        $terminatingJob = $client->getJob($job['id']);

        self::assertEquals('terminating', $terminatingJob['desiredStatus']);
    }
}
