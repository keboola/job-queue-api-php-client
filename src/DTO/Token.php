<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\DTO;

readonly class Token
{
    private function __construct(
        public string $id,
        public string $description,
    ) {
    }

    public static function fromApiResponse(array $response): self
    {
        return new self(
            id: (string) $response['id'],
            description: (string) $response['description'],
        );
    }
}
