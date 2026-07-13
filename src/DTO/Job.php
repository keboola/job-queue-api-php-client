<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient\DTO;

use DateTimeImmutable;
use Keboola\ApiClientBase\ResponseModelInterface;
use Keboola\JobQueueClient\JobStatuses;

final readonly class Job implements ResponseModelInterface
{
    private function __construct(
        public string $id,
        public string $runId,
        public string $parentRunId,
        public Project $project,
        public Token $token,
        /** @var string JobStatuses::* */
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
        public ?string $parallelism,
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
        return self::fromResponseData($response);
    }

    public static function fromResponseData(array $data): static
    {
        return new self(
            id: $data['id'],
            runId: $data['runId'],
            parentRunId: $data['parentRunId'],
            project: Project::fromApiResponse($data['project']),
            token: Token::fromApiResponse($data['token']),
            status: $data['status'],
            desiredStatus: $data['desiredStatus'],
            mode: $data['mode'],
            component: $data['component'],
            config: $data['config'],
            configData: $data['configData'],
            configRowIds: $data['configRowIds'],
            tag: $data['tag'],
            createdTime: new DateTimeImmutable($data['createdTime']),
            startTime: is_string($data['startTime']) ? new DateTimeImmutable($data['startTime']) : null,
            endTime: is_string($data['endTime']) ? new DateTimeImmutable($data['endTime']) : null,
            durationSeconds: $data['durationSeconds'],
            result: $data['result'],
            usageData: $data['usageData'],
            isFinished: $data['isFinished'],
            url: $data['url'],
            branchId: $data['branchId'],
            variableValuesId: $data['variableValuesId'],
            variableValuesData: VariableValuesData::fromApiResponse($data['variableValuesData']),
            backend: Backend::fromApiResponse($data['backend']),
            executor: $data['executor'],
            metrics: $data['metrics'],
            behavior: Behavior::fromApiResponse($data['behavior']),
            parallelism: $data['parallelism'],
            type: $data['type'],
            orchestrationJobId: $data['orchestrationJobId'],
            orchestrationTaskId: $data['orchestrationTaskId'],
            onlyOrchestrationTaskIds: $data['onlyOrchestrationTaskIds'],
            previousJobId: $data['previousJobId'],
        );
    }

    public function isSuccess(): bool
    {
        return $this->status === JobStatuses::SUCCESS->value;
    }

    public function isError(): bool
    {
        return $this->status === JobStatuses::ERROR->value;
    }
}
