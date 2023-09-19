<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\Tests;

use Keboola\JobQueueClient\JobData;
use PHPUnit\Framework\TestCase;

class JobDataTest extends TestCase
{
    public function testAccessorsMin(): void
    {
        $jobData = new JobData('dummy', 'config');
        self::assertEquals(
            [
                'component' => 'dummy',
                'config' => 'config',
                'mode' => 'run',
                'configRowIds' => [],
                'tag' => null,
                'branchId' => null,
                'orchestrationJobId' => null,
                'configData' => [],
            ],
            $jobData->getArray(),
        );
    }

    public function testAccessorsFull(): void
    {
        $jobData = new JobData(
            'dummy',
            'config',
            ['foo' => 'bar'],
            'debug',
            ['1', '2'],
            '1.2.3',
            '123',
            '123456',
        );

        self::assertEquals(
            [
                'component' => 'dummy',
                'config' => 'config',
                'mode' => 'debug',
                'configRowIds' => ['1', '2'],
                'tag' => '1.2.3',
                'branchId' => '123',
                'orchestrationJobId' => '123456',
                'configData' => ['foo' => 'bar'],
            ],
            $jobData->getArray(),
        );
    }
}
