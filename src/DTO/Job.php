<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\DTO;

readonly class Job
{
    /**
     * @param array{error?: array{type:string, exceptionId:string}, message: string}|array{} $result
     */
    public function __construct(
        public string $id,
        public string $runId,
        public string $parentRunId,
        public Project $project,
        public Token $token,
        public string $status,
        public string $desiredStatus,
        public string $mode,
        public string $component,
        public ?string $config,
        public array $configData,
        public ?array $configRowIds,
        public ?string $tag,
        public string $createdTime,
        public ?string $startTime,
        public ?string $endTime,
        public int $durationSeconds,
        public array $result,
        public array $usageData,
        public bool $isFinished,
        public string $url,
        public string $branchId,
        public ?string $variableValuesId,
        public VariableValuesData $variableValuesData,
        public Backend $backend,
        public string $executor,
        public array $metrics,
        public Behavior $behavior,
        public ?int $parallelism,
        public string $type,
        public ?string $orchestrationJobId,
        public ?string $orchestrationTaskId,
        public ?array $onlyOrchestrationTaskIds,
        public ?string $previousJobId,
    ) {
    }

    public static function fromApiResponse(array $response): self
    {
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
            createdTime: $response['createdTime'],
            startTime: $response['startTime'],
            endTime: $response['endTime'],
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
    }

    public function isSuccess(): bool
    {
        return $this->status === JobStates::SUCCESS->value;
    }

    public function isError(): bool
    {
        return $this->status === JobStates::ERROR->value;
    }
}
