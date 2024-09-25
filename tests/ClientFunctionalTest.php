<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\Tests;

use DateTime;
use Generator;
use Keboola\JobQueueClient\Client;
use Keboola\JobQueueClient\DTO\Job;
use Keboola\JobQueueClient\Exception\ClientException;
use Keboola\JobQueueClient\JobData;
use Keboola\JobQueueClient\JobStatuses;
use Keboola\JobQueueClient\ListJobsOptions;
use Keboola\StorageApi\Client as StorageClient;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use PHPUnit\Framework\TestCase;
use Throwable;

class ClientFunctionalTest extends TestCase
{
    private const COMPONENT_ID = 'keboola.ex-db-snowflake';
    private const COMPONENT_ID_2 = 'keboola.ex-db-mysql';
    private const COMPONENT_ID_3 = 'keboola.ex-db-pgsql';

    private function getClient(array $options = []): Client
    {
        return new Client(
            (string) getenv('public_queue_api_url'),
            (string) getenv('test_storage_api_token'),
            $options,
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
            'public-api-test',
        )['id'];

        $client = $this->getClient();
        $createdJob = $client->createJob(new JobData(
            self::COMPONENT_ID,
            $configurationId,
            [],
        ));

        self::assertNotEmpty($createdJob->id);
        self::assertEquals('created', $createdJob->status);

        self::deleteConfiguration(self::COMPONENT_ID, $configurationId);
    }

    public function testCreateJobWithConfigData(): void
    {
        $client = $this->getClient();
        $createdJob = $client->createJob(new JobData(
            self::COMPONENT_ID,
            null,
            [
                'parameters' => [
                    'foo' => 'bar',
                ],
            ],
        ));

        self::assertNotEmpty($createdJob->id);
        self::assertEquals('created', $createdJob->status);
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
        $createdJob = $client->createJob(new JobData(
            self::COMPONENT_ID,
            '',
            [],
        ));
        $retrievedJob = $client->getJob($createdJob->id);
        self::assertEquals($createdJob->id, $retrievedJob->id);
        self::assertEquals('created', $retrievedJob->status);
    }

    /** @dataProvider listJobsFilterProvider() */
    public function testListJobsFilter(
        ListJobsOptions $listOptions,
        array $expectedJobs,
    ): void {
        $response = $this->getClient()->listJobs($listOptions);
        self::assertNotEmpty($response);
        self::assertEquals(
            array_map(fn(Job $job) => $job->id, $expectedJobs),
            array_map(fn(Job $job) => $job->id, $response),
        );
    }

    public function listJobsFilterProvider(): Generator
    {
        $configurationId = self::createConfiguration(
            self::COMPONENT_ID,
            'public-api-test',
        )['id'];
        $configurationId2 = self::createConfiguration(
            self::COMPONENT_ID_2,
            'public-api-test-2',
        )['id'];
        $configurationId3 = self::createConfiguration(
            self::COMPONENT_ID_2,
            'public-api-test-3',
        )['id'];

        $client = $this->getClient();
        $job1 = $client->createJob(new JobData(
            self::COMPONENT_ID,
            $configurationId,
        ));
        $job2 = $client->createJob(new JobData(
            self::COMPONENT_ID,
            $configurationId,
        ));
        $job3 = $client->createJob(new JobData(
            self::COMPONENT_ID_2,
            $configurationId2,
        ));
        $client->createJob(new JobData(
            self::COMPONENT_ID_2,
            $configurationId3,
        ));
        $client->createJob(new JobData(
            self::COMPONENT_ID_3,
            '',
            [],
        ));

        $tokenRes = $this->getStorageClient()->verifyToken();
        $projectId = $tokenRes['owner']['id'];

        yield 'By configs' => [
            'listJobOptions' => (new ListJobsOptions())->setConfigs([
                $configurationId,
                $configurationId2,
            ]),
            'expectedJobs' => [
                $job3,
                $job2,
                $job1,
            ],
        ];
        yield 'By components' => [
            'listJobOptions' => (new ListJobsOptions())
                ->setConfigs([$configurationId])
                ->setComponents([self::COMPONENT_ID]),
            'expectedJobs' => [
                $job2,
                $job1,
            ],
        ];
        yield 'By project' => [
            'listJobOptions' => (new ListJobsOptions())
                ->setConfigs([$configurationId])
                ->setProjects([$projectId]),
            'expectedJobs' => [
                $job2,
                $job1,
            ],
        ];
        yield 'By statuses' => [
            'listJobOptions' => (new ListJobsOptions())
                ->setConfigs([$configurationId])
                ->setStatuses([JobStatuses::CREATED]),
            'expectedJobs' => [
                $job2,
                $job1,
            ],
        ];
        yield 'By startTime' => [
            'listJobOptions' => (new ListJobsOptions())
                ->setConfigs([$configurationId])
                ->setCreatedTimeFrom(new DateTime('-1 hour'))
                ->setCreatedTimeTo(new DateTime('now')),
            'expectedJobs' => [
                $job2,
                $job1,
            ],
        ];
        yield 'By token id' => [
            'listJobOptions' => (new ListJobsOptions())
                ->setConfigs([$configurationId])
                ->setTokenIds([$tokenRes['id']]),
            'expectedJobs' => [
                $job2,
                $job1,
            ],
        ];

        self::deleteConfiguration(self::COMPONENT_ID, $configurationId);
        self::deleteConfiguration(self::COMPONENT_ID_2, $configurationId2);
        self::deleteConfiguration(self::COMPONENT_ID_2, $configurationId3);
    }

    public function testListJobsLimit(): void
    {
        $client = $this->getClient();
        $client->createJob(new JobData(
            self::COMPONENT_ID_3,
            '',
            [],
        ));
        $client->createJob(new JobData(
            self::COMPONENT_ID_3,
            '',
            [],
        ));
        $client->createJob(new JobData(
            self::COMPONENT_ID_3,
            '',
            [],
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
            [],
        ));
        $terminatedJob = $client->terminateJob($job->id);
        self::assertEquals('terminating', $terminatedJob->desiredStatus);
        $terminatingJob = $client->getJob($job->id);
        self::assertEquals('terminating', $terminatingJob->desiredStatus);
    }
}
