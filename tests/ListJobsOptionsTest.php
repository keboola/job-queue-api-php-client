<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\Tests;

use DateTimeImmutable;
use Keboola\JobQueueClient\Exception\ClientException;
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
        $options->setConfigs(['a', 'b', 'c']);
        $options->setConfigRowIds(['d', 'e', 'f']);
        $options->setProjects(['12', '13']);
        $options->setModes(['run', 'debug']);
        $options->setStatuses([ListJobsOptions::STATUS_SUCCESS, ListJobsOptions::STATUS_PROCESSING]);
        $options->setParentRunId('123');
        $options->setType(ListJobsOptions::TYPE_STANDARD);
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
            $options->getTokenDescriptions()
        );
        self::assertSame(['writer', 'extractor', 'orchestrator'], $options->getComponents());
        self::assertSame(['a', 'b', 'c'], $options->getConfigs());
        self::assertSame(['d', 'e', 'f'], $options->getConfigRowIds());
        self::assertSame(['12', '13'], $options->getProjects());
        self::assertSame(['run', 'debug'], $options->getModes());
        self::assertSame(
            [ListJobsOptions::STATUS_SUCCESS, ListJobsOptions::STATUS_PROCESSING],
            $options->getStatuses()
        );
        self::assertSame('123', $options->getParentRunId());
        self::assertSame(ListJobsOptions::TYPE_STANDARD, $options->getType());
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
            'id[]=1',
            'id[]=2',
            'id[]=3',
            'runId[]=5',
            'runId[]=6',
            'runId[]=7',
            'branchId[]=branch1',
            'branchId[]=branch2',
            'branchId[]=branch3',
            'tokenId[]=8',
            'tokenId[]=9',
            'tokenId[]=10',
            'tokenDescription[]=new+token',
            'tokenDescription[]=old+token',
            'tokenDescription[]=bad+token',
            'tokenDescription[]=good+token',
            'component[]=writer',
            'component[]=extractor',
            'component[]=orchestrator',
            'config[]=a',
            'config[]=b',
            'config[]=c',
            'configRowIds[]=d',
            'configRowIds[]=e',
            'configRowIds[]=f',
            'mode[]=run',
            'mode[]=debug',
            'projectId[]=12',
            'projectId[]=13',
            'status[]=success',
            'status[]=processing',
            'durationSecondsFrom=5',
            'durationSecondsTo=7200',
            'offset=20',
            'limit=100',
            'sortBy=id',
            'sortOrder=desc',
            'type=standard',
            'parentRunId=123',
            'startTimeFrom=' . urlencode('2021-02-02T01:12:23+00:00'),
            'startTimeTo=' . urlencode('2021-02-20T01:12:23+00:00'),
            'createdTimeFrom=' . urlencode('2022-02-02T01:12:23+00:00'),
            'createdTimeTo=' . urlencode('2022-02-20T01:12:23+00:00'),
            'endTimeFrom=' . urlencode('2020-02-02T01:12:23+00:00'),
            'endTimeTo=' . urlencode('2020-02-20T01:12:23+00:00'),
        ];

        self::assertSame($expected, $options->getQueryParameters());
    }

    public function testGetQueryParametersForParametersWithEmptyValueAllowed(): void
    {
        // default values
        $jobListOptions = new ListJobsOptions();

        self::assertSame(['limit=100'], $jobListOptions->getQueryParameters());
        self::assertNull($jobListOptions->getParentRunId());

        // empty string
        $jobListOptions->setParentRunId('');
        self::assertSame(
            [
                'limit=100',
                'parentRunId=',
            ],
            $jobListOptions->getQueryParameters()
        );
        self::assertSame('', $jobListOptions->getParentRunId());

        // null
        $jobListOptions->setParentRunId(null);
        self::assertSame(
            [
                'limit=100',
            ],
            $jobListOptions->getQueryParameters()
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
