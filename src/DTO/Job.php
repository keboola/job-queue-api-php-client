<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\DTO;

use DateTimeImmutable;
use Keboola\JobQueueClient\Exception\ClientException;
use Throwable;

readonly class Job
{
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_CREATED = 'created';
    public const STATUS_ERROR = 'error';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_TERMINATED = 'terminated';
    public const STATUS_TERMINATING = 'terminating';
    public const STATUS_WAITING = 'waiting';
    public const STATUS_WARNING = 'warning';

    public function __construct(
        public string $id,
        public string $runId,
        public string $parentRunId,
        public Project $project,
        public Token $token,
        /** @var string self::STATUS_* */
        public string $status,
        /** @var string "processing"|"terminating" */
        public string $desiredStatus,
        /** @var string "run"|"forceRun"|"debug"  */
        public string $mode,
        public string $component,
        public ?string $config,
        public ?array $configData,
        public ?array $configRowIds,
        public ?string $tag,
        public DateTimeImmutable $createdTime,
        public ?DateTimeImmutable $startTime,
        public ?DateTimeImmutable $endTime,
        public ?int $durationSeconds,
        public ?array $result,
        public ?array $usageData,
        public bool $isFinished,
        public string $url,
        public ?string $branchId,
        public ?string $variableValuesId,
        public ?VariableValuesData $variableValuesData,
        public ?Backend $backend,
        public ?string $executor,
        public ?array $metrics,
        public ?Behavior $behavior,
        public ?int $parallelism,
        /** @var string "standard"|"container"|"phaseContainer"|"orchestrationContainer" */
        public string $type,
        public ?string $orchestrationJobId,
        public ?string $orchestrationTaskId,
        public ?array $onlyOrchestrationTaskIds,
        public ?string $previousJobId,
    ) {
    }

    public static function fromApiResponse(array $response): self
    {
        try {
            return new self(
                id: $response['id'],
                runId: $response['runId'],
                parentRunId: $response['parentRunId'],
                project: Project::fromApiResponse($response['project']),
                token: Token::fromApiResponse($response['token']),
                status: $response['status'],
                desiredStatus: $response['desiredStatus'],
                mode: $response['mode'],
                component: $response['component'],
                config: $response['config'],
                configData: $response['configData'],
                configRowIds: $response['configRowIds'],
                tag: $response['tag'],
                createdTime: new DateTimeImmutable($response['createdTime']),
                startTime: is_string($response['startTime']) ? new DateTimeImmutable($response['startTime']) : null,
                endTime: is_string($response['endTime']) ? new DateTimeImmutable($response['endTime']) : null,
                durationSeconds: $response['durationSeconds'],
                result: $response['result'],
                usageData: $response['usageData'],
                isFinished: $response['isFinished'],
                url: $response['url'],
                branchId: $response['branchId'],
                variableValuesId: $response['variableValuesId'],
                variableValuesData: VariableValuesData::fromApiResponse($response['variableValuesData']),
                backend: Backend::fromApiResponse($response['backend']),
                executor: $response['executor'],
                metrics: $response['metrics'],
                behavior: Behavior::fromApiResponse($response['behavior']),
                parallelism: $response['parallelism'],
                type: $response['type'],
                orchestrationJobId: $response['orchestrationJobId'],
                orchestrationTaskId: $response['orchestrationTaskId'],
                onlyOrchestrationTaskIds: $response['onlyOrchestrationTaskIds'],
                previousJobId: $response['previousJobId'],
            );
        } catch (Throwable $e) {
            throw new ClientException('Failed to parse Job data: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isError(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }
}
