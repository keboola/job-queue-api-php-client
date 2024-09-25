<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\DTO;

use Keboola\JobQueueClient\Exception\ClientException;
use Throwable;

readonly class Token
{
    private function __construct(
        public string $id,
        public ?string $description,
    ) {
    }

    public static function fromApiResponse(array $response): self
    {
        return new self(
            id: $response['id'],
            description: $response['description'],
        );
    }
}
