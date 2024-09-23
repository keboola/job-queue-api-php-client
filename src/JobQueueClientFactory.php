<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient;

use Keboola\JobQueueClient\Client;
use SensitiveParameter;

class JobQueueClientFactory
{
    public function __construct(
        private readonly string $publicApiUrl,
        private readonly string $userAgent,
    ) {
    }

    public function createClientFromToken(#[SensitiveParameter] string $token): Client
    {
        return new Client(
            $this->publicApiUrl,
            $token,
            ['userAgent' => $this->userAgent],
        );
    }
}
