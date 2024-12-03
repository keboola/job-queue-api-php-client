<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\Tests;

use DateTimeImmutable;
use Keboola\JobQueueClient\Exception\ClientException;
use Keboola\JobQueueClient\JobStatuses;
use Keboola\JobQueueClient\JobType;
use Keboola\JobQueueClient\ListJobsOptions;
use PHPUnit\Framework\TestCase;

class ListJobsOptionsTest extends TestCase
{
    public function testGetQueryParameters(): void
    {
        $options = new ListJobsOptions();

        $options->setIds(['1', '2', '3']);
        $options->setRunIds(['5', '6', '7']);
        $options->setBranchIds(['branch1', 'branch2', 'branch3']);
        $options->setTokenIds(['8', '9', '10']);
        $options->setTokenDescriptions(['new token', 'old token', 'bad token', 'good token']);
        $options->setComponents(['writer', 'extractor', 'orchestrator']);
        $options->setConfigIds(['a', 'b', 'c']);
        $options->setConfigRowIds(['d', 'e', 'f']);
        $options->setModes(['run', 'debug']);
        $options->setStatuses([JobStatuses::SUCCESS, JobStatuses::PROCESSING]);
        $options->setParentRunId('123');
        $options->setType(JobType::STANDARD);
        $options->setCreatedTimeFrom(new DateTimeImmutable('2022-02-02 1:12:23'));
        $options->setCreatedTimeTo(new DateTimeImmutable('2022-02-20 1:12:23'));
        $options->setStartTimeFrom(new DateTimeImmutable('2021-02-02 1:12:23'));
        $options->setStartTimeTo(new DateTimeImmutable('2021-02-20 1:12:23'));
        $options->setEndTimeFrom(new DateTimeImmutable('2020-02-02 1:12:23'));
        $options->setEndTimeTo(new DateTimeImmutable('2020-02-20 1:12:23'));
        $options->setDurationSecondsFrom(5);
        $options->setDurationSecondsTo(7200);
        $options->setSortOrder(ListJobsOptions::SORT_ORDER_DESC);
        $options->setSortBy('id');
        $options->setOffset(20);
        $options->setLimit(100);

        self::assertSame(['1', '2', '3'], $options->getIds());
        self::assertSame(['5', '6', '7'], $options->getRunIds());
        self::assertSame(['branch1', 'branch2', 'branch3'], $options->getBranchIds());
        self::assertSame(['8', '9', '10'], $options->getTokenIds());
        self::assertSame(
            ['new token', 'old token', 'bad token', 'good token'],
            $options->getTokenDescriptions(),
        );
        self::assertSame(['writer', 'extractor', 'orchestrator'], $options->getComponents());
        self::assertSame(['a', 'b', 'c'], $options->getConfigIds());
        self::assertSame(['d', 'e', 'f'], $options->getConfigRowIds());
        self::assertSame(['run', 'debug'], $options->getModes());
        self::assertSame(
            [JobStatuses::SUCCESS, JobStatuses::PROCESSING],
            $options->getStatuses(),
        );
        self::assertSame('123', $options->getParentRunId());
        self::assertSame(JobType::STANDARD, $options->getType());
        self::assertSame('2022-02-02 01:12:23', $options->getCreatedTimeFrom()->format('Y-m-d H:i:s'));
        self::assertSame('2022-02-20 01:12:23', $options->getCreatedTimeTo()->format('Y-m-d H:i:s'));
        self::assertSame('2021-02-02 01:12:23', $options->getStartTimeFrom()->format('Y-m-d H:i:s'));
        self::assertSame('2021-02-20 01:12:23', $options->getStartTimeTo()->format('Y-m-d H:i:s'));
        self::assertSame('2020-02-02 01:12:23', $options->getEndTimeFrom()->format('Y-m-d H:i:s'));
        self::assertSame('2020-02-20 01:12:23', $options->getEndTimeTo()->format('Y-m-d H:i:s'));
        self::assertSame(5, $options->getDurationSecondsFrom());
        self::assertSame(7200, $options->getDurationSecondsTo());
        self::assertSame('id', $options->getSortBy());
        self::assertSame(ListJobsOptions::SORT_ORDER_DESC, $options->getSortOrder());
        self::assertSame(20, $options->getOffset());
        self::assertSame(100, $options->getLimit());

        $expected = [
            'id' => ['1','2','3'],
            'runId' => ['5','6','7'],
            'branchId' => ['branch1', 'branch2', 'branch3'],
            'tokenId' => ['8','9','10'],
            'tokenDescription' => ['new token', 'old token', 'bad token', 'good token'],
            'component' => ['writer', 'extractor', 'orchestrator'],
            'configId' => ['a', 'b', 'c'],
            'configRowIds' => ['d', 'e', 'f'],
            'mode' => ['run', 'debug'],
            'durationSecondsFrom' => 5,
            'durationSecondsTo' => 7200,
            'offset' => 20,
            'limit' => 100,
            'sortBy' => 'id',
            'sortOrder' => 'desc',
            'type' => 'standard',
            'status' => [JobStatuses::SUCCESS->value, JobStatuses::PROCESSING->value],
            'parentRunId' => '123',
            'startTimeFrom' => ('2021-02-02T01:12:23+00:00'),
            'startTimeTo' => '2021-02-20T01:12:23+00:00',
            'createdTimeFrom' => '2022-02-02T01:12:23+00:00',
            'createdTimeTo' => '2022-02-20T01:12:23+00:00',
            'endTimeFrom' => '2020-02-02T01:12:23+00:00',
            'endTimeTo' => '2020-02-20T01:12:23+00:00',
        ];

        self::assertSame($expected, $options->getQueryParameters());
    }

    public function testGetQueryParametersForParametersWithEmptyValueAllowed(): void
    {
        // default values
        $jobListOptions = new ListJobsOptions();

        self::assertSame(['limit' => 100], $jobListOptions->getQueryParameters());
        self::assertNull($jobListOptions->getParentRunId());

        // empty string
        $jobListOptions->setParentRunId('');
        self::assertSame(
            [
                'limit' => 100,
                'parentRunId' => '',
            ],
            $jobListOptions->getQueryParameters(),
        );
        self::assertSame('', $jobListOptions->getParentRunId());

        // null
        $jobListOptions->setParentRunId(null);
        self::assertSame(
            [
                'limit' => 100,
            ],
            $jobListOptions->getQueryParameters(),
        );
        self::assertNull($jobListOptions->getParentRunId());
    }

    public function testSetSortOrderWrong(): void
    {
        $jobListOptions = new ListJobsOptions();

        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Allowed values for "sortOrder" are [asc, desc].');
        $jobListOptions->setSortOrder('left');
    }
}
