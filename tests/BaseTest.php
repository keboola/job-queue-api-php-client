<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\Tests;

use Exception;
use PHPUnit\Framework\TestCase;

abstract class BaseTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $requiredEnvs = ['public_queue_api_url', 'test_storage_api_token'];
        foreach ($requiredEnvs as $env) {
            if (empty(getenv($env))) {
                throw new Exception(sprintf('Environment variable "%s" is empty', $env));
            }
        }
    }
}
