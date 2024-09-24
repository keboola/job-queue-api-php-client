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
        try {
            return new self(
                onError: $response['onError'] ?? null,
            );
        } catch (Throwable $e) {
            throw new ClientException('Failed to parse Behavior data: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
