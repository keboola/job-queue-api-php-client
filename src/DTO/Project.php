<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\DTO;

use Keboola\JobQueueClient\Exception\ClientException;
use Throwable;

readonly class Project
{
    private function __construct(
        public string $id,
    ) {
    }

    public static function fromApiResponse(array $response): self
    {
        return new self(
            id: $response['id'],
        );
    }
}
