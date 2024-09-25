<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient;

enum JobStatuses: string
{
    case CREATED = 'created';
    case PROCESSING = 'processing';
    case TERMINATING = 'terminating';
    case TERMINATED = 'terminated';
    case WAITING = 'waiting';
    case SUCCESS = 'success';
    case ERROR = 'error';
    case WARNING = 'warning';
    case CANCELLED = 'cancelled';
}
