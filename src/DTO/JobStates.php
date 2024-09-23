<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\DTO;

enum JobStates: string
{
    case CANCELLED = 'cancelled';
    case CREATED = 'created';
    case ERROR = 'error';
    case PROCESSING = 'processing';
    case SUCCESS = 'success';
    case TERMINATED = 'terminated';
    case TERMINATING = 'terminating';
    case WAITING = 'waiting';
    case WARNING = 'warning';
}
