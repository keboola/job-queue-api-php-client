<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\DTO;

use Keboola\JobQueueClient\Exception\ClientException;
use Throwable;

readonly class Behavior
{
    private function __construct(
        public ?string $onError,
    ) {
    }

    public static function fromApiResponse(array $response): self
    {
        return new self(
            onError: $response['onError'] ?? null,
        );
    }
}
