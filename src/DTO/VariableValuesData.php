<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\DTO;

use Keboola\JobQueueClient\Exception\ClientException;
use Throwable;

class VariableValuesData
{
    private function __construct(
        public ?array $values,
    ) {
    }

    public static function fromApiResponse(array $response): self
    {
        try {
            return new self(
                values: $response['values'] ?? null,
            );
        } catch (Throwable $e) {
            throw new ClientException('Failed to parse variableValuesData: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
