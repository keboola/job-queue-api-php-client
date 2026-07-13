<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient;

use Keboola\ApiClientBase\ResponseModelInterface;

final readonly class ArrayResponse implements ResponseModelInterface
{
    public function __construct(
        public array $data,
    ) {
    }

    public static function fromResponseData(array $data): static
    {
        return new self($data);
    }
}
