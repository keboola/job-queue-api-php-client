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
        try {
            return new self(
                id: $response['id'],
                description: $response['description'],
            );
        } catch (Throwable $e) {
            throw new ClientException('Failed to parse Token data: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
