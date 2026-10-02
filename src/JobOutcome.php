<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient;

/**
 * Whether a finished job delivered what it was asked to do.
 *
 * - The job's `outcome`; the API derives it from `status`, and the rule is `JobOutcome` in the
 *   public API's swagger.yaml.
 */
enum JobOutcome: string
{
    case SUCCESS = 'success';
    case FAILURE = 'failure';
}
