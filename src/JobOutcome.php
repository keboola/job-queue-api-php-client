<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient;

/**
 * Whether a finished job delivered what it was asked to do.
 *
 * - The job's `outcome`, which the API derives from `status`. A job that has not finished has none.
 * - `SUCCESS` covers `success` and `warning`; `FAILURE` covers `error`, `cancelled` and
 *   `terminated`.
 * - `warning` is a success because the configuration asked for it: a child job failed and its
 *   `behavior.onError` was `warning`, so the container tolerated the failure and went on.
 * - `cancelled` and `terminated` are failures of "did this job deliver", not of the job itself — a
 *   user stopped it, and neither produced the job's output.
 */
enum JobOutcome: string
{
    case SUCCESS = 'success';
    case FAILURE = 'failure';
}
