<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\Tests;

use Keboola\JobQueueClient\Client;
use Keboola\JobQueueClient\JobQueueClientFactory;
use PHPUnit\Framework\TestCase;

class JobQueueClientFactoryTest extends TestCase
{
    public function testCreateClientFromToken(): void
    {
        $factory = new JobQueueClientFactory('https://example.com', 'user-agent');
        $client = $factory->createClientFromToken('token');

        self::assertInstanceOf(Client::class, $client);
    }
}
