<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\DTO;

readonly class Behavior
{
    private function __construct(
        public ?string $onError,
    ) {
    }

    public static function fromApiResponse(array $response): self
    {
        return new self(
            onError: $response['onError'],
        );
    }
}
