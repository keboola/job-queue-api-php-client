<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient;

use Keboola\JobQueueClient\Client;
use SensitiveParameter;
use Webmozart\Assert\Assert;

class JobQueueClientFactory
{
    public function __construct(
        private readonly string $publicApiUrl,
        private readonly string $userAgent,
    ) {
    }

    public function createClientFromToken(#[SensitiveParameter] string $token): Client
    {
        Assert::stringNotEmpty($this->publicApiUrl, 'Public API URL must be a non-empty string.');
        Assert::stringNotEmpty($token, 'Storage API token must be a non-empty string.');

        return new Client(
            $this->publicApiUrl,
            $token,
            userAgent: $this->userAgent,
        );
    }
}
