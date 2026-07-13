<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\DTO;

use Keboola\ApiClientBase\ResponseModelInterface;

final readonly class ProjectStats implements ResponseModelInterface
{
    public function __construct(
        public int $jobsDurationSum,
    ) {
    }

    public static function fromResponseData(array $data): static
    {
        /** @var array{jobs?: array{durationSum?: int|numeric-string}} $data */
        return new self(
            jobsDurationSum: (int) ($data['jobs']['durationSum'] ?? 0),
        );
    }
}
