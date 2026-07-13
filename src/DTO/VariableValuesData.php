<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\DTO;

class VariableValuesData
{
    private function __construct(
        public ?array $values,
    ) {
    }

    public static function fromResponseData(array $response): self
    {
        return new self(
            values: $response['values'] ?? null,
        );
    }
}
