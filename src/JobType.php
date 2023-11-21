<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient;

enum JobType: string
{
    case STANDARD = 'standard';
    case ROW_CONTAINER = 'container';
    case PHASE_CONTAINER = 'phaseContainer';
    case ORCHESTRATION_CONTAINER = 'orchestrationContainer';
}
