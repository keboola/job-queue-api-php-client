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
        return new self(
            values: $response['values'] ?? null,
        );
    }
}
