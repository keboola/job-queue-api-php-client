<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\Tests\DTO;

use Generator;
use Keboola\JobQueueClient\DTO\Job;
use PHPUnit\Framework\TestCase;
use Throwable;
use TypeError;

class JobTest extends TestCase
{
    private array $validJobData = [
        'id' => '3861921',
        'runId' => '3861921',
        'parentRunId' => '',
        'project' => [
            'id' => '18',
        ],
        'token' => [
            'id' => '209',
            'description' => null,
        ],
        'status' => 'created',
        'desiredStatus' => 'processing',
        'mode' => 'run',
        'component' => 'keboola.snowflake-transformation',
        'config' => null,
        'configData' => null,
        'configRowIds' => null,
        'tag' => '1.0.1',
        'createdTime' => '2024-09-23T11:41:52+00:00',
        'startTime' => null,
        'endTime' => null,
        'durationSeconds' => 0,
        'result' => [],
        'usageData' => [],
        'isFinished' => false,
        'url' => 'https://queue.east-us-2.azure.keboola-testing.com/jobs/3861921',
        'branchId' => '6',
        'variableValuesId' => null,
        'variableValuesData' => [],
        'backend' => [],
        'executor' => 'dind',
        'metrics' => [],
        'behavior' => [],
        'parallelism' => null,
        'type' => 'standard',
        'orchestrationJobId' => null,
        'orchestrationTaskId' => null,
        'onlyOrchestrationTaskIds' => null,
        'previousJobId' => null,
    ];

    /**
     * @dataProvider invalidJobDataProvider
     * @param class-string<Throwable> $expectedExceptionClass
     */
    public function testCreateInvalid(
        array $invalidData,
        string $expectedMessage,
        int $expectedCode,
        string $expectedExceptionClass,
    ): void {
        $this->expectException($expectedExceptionClass);
        $this->expectExceptionMessageMatches($expectedMessage);
        $this->expectExceptionCode($expectedCode);
        Job::fromApiResponse($invalidData);
    }

    public function testCreateValid(): void
    {
        $job = Job::fromApiResponse($this->validJobData);
        self::assertSame('3861921', $job->id);
        self::assertFalse($job->isFinished);
        self::assertFalse($job->isError());
        self::assertFalse($job->isSuccess());
    }

    public function invalidJobDataProvider(): Generator
    {
        yield 'missing base fields' => [
            'jobData' => [
                'id' => '123',
                'runId' => '456',
                'parentRunId' => 3432,
                'project' => 'invalid',
            ],
            'expectedMessage' => '#Argument \\#1 \\(\\$response\\) must be of type array, string given#',
            'expectedCode' => 0,
            'expectedException' => TypeError::class,
        ];

        $jobData = $this->validJobData;
        $jobData['token']['id'] = [];
        yield 'invalid token data' => [
            'jobData' => $jobData,
            'expectedMessage' => '#Argument \\#1 \\(\\$id\\) must be of type string, array given#',
            'expectedCode' => 0,
            'expectedException' => TypeError::class,
        ];

        $jobData = $this->validJobData;
        $jobData['variableValuesData']['values'] = 'invalid';
        yield 'broken variable values' => [
            'jobData' => $jobData,
            // phpcs:ignore Generic.Files.LineLength
            'expectedMessage' => '#Argument \\#1 \\(\\$values\\) must be of type \\?array, string given#',
            'expectedCode' => 0,
            'expectedException' => TypeError::class,
        ];

        $jobData = $this->validJobData;
        $jobData['project']['id'] = [];
        yield 'broken project data' => [
            'jobData' => $jobData,
            'expectedMessage' => '#Argument \\#1 \\(\\$id\\) must be of type string, array given#',
            'expectedCode' => 0,
            'expectedException' => TypeError::class,
        ];

        $jobData = $this->validJobData;
        $jobData['createdTime'] = 'invalid';
        yield 'broken created time' => [
            'jobData' => $jobData,
            // phpcs:ignore Generic.Files.LineLength
            'expectedMessage' => '#Failed to parse time string \\(invalid\\) at position 0 \\(i\\)#',
            'expectedCode' => 0,
            'expectedException' => Throwable::class,
        ];

        $jobData = $this->validJobData;
        $jobData['behavior']['onError'] = [];
        yield 'broken behavior' => [
            'jobData' => $jobData,
            // phpcs:ignore Generic.Files.LineLength
            'expectedMessage' => '#Argument \\#1 \\(\\$onError\\) must be of type \\?string, array given#',
            'expectedCode' => 0,
            'expectedException' => TypeError::class,
        ];

        $jobData = $this->validJobData;
        $jobData['backend']['context'] = [];
        yield 'broken backend' => [
            'jobData' => $jobData,
            // phpcs:ignore Generic.Files.LineLength
            'expectedMessage' => '#Argument \\#1 \\(\\$context\\) must be of type \\?string, array given#',
            'expectedCode' => 0,
            'expectedException' => TypeError::class,
        ];
    }
}
