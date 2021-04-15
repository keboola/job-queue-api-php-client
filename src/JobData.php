<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient;

class JobData
{
    private string $componentId;
    private string $configId;
    private array $configData;
    private string $mode;
    private array $configRowIds;
    private ?string $tag;
    private ?string $branchId;

    public function __construct(
        string $componentId,
        string $configId,
        array $configData = [],
        string $mode = 'run',
        array $configRowIds = [],
        ?string $tag = null,
        ?string $branchId = null
    ) {

        $this->componentId = $componentId;
        $this->configId = $configId;
        $this->configData = $configData;
        $this->mode = $mode;
        $this->configRowIds = $configRowIds;
        $this->tag = $tag;
        $this->branchId = $branchId;
    }

    public function getArray(): array
    {
        return [
            'component' => $this->componentId,
            'config' => $this->configId,
            'mode' => $this->mode,
            'configRowIds' => $this->configRowIds,
            'tag' => $this->tag,
            'branchId' => $this->branchId,
            'configData' => $this->configData,
        ];
    }
}
