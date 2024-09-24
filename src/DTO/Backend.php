<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\DTO;

use Keboola\JobQueueClient\Exception\ClientException;
use Throwable;

readonly class Backend
{
    public function __construct(
        public ?string $context,
        public ?string $containerType,
        public ?string $type,
    ) {
    }

    public static function fromApiResponse(array $response): self
    {
        try {
            return new self(
                context: $response['context'] ?? null,
                containerType: $response['containerType'] ?? null,
                type: $response['type'] ?? null,
            );
        } catch (Throwable $e) {
            throw new ClientException('Failed to parse Backend data: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
