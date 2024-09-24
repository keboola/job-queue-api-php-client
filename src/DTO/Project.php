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
        try {
            return new self(
                id: $response['id'],
            );
        } catch (Throwable $e) {
            throw new ClientException('Failed to parse Project data: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
