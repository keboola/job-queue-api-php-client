<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\DTO;

readonly class Token
{
    private function __construct(
        public string $id,
        public ?string $description,
    ) {
    }

    public static function fromResponseData(array $response): self
    {
        return new self(
            id: $response['id'],
            description: $response['description'],
        );
    }
}
