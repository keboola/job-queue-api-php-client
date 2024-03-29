<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient;

class JobData
{
    /** @var string */
    private $componentId;
    /** @var string|null */
    private $configId;
    /** @var array */
    private $configData;
    /** @var string */
    private $mode;
    /** @var array */
    private $configRowIds;
    /** @var string|null */
    private $tag;
    /** @var string|null */
    private $branchId;
    /** @var string|null */
    private $orchestrationJobId;

    public function __construct(
        string $componentId,
        ?string $configId = null,
        array $configData = [],
        string $mode = 'run',
        array $configRowIds = [],
        ?string $tag = null,
        ?string $branchId = null,
        ?string $orchestrationJobId = null,
    ) {

        $this->componentId = $componentId;
        $this->configId = $configId;
        $this->configData = $configData;
        $this->mode = $mode;
        $this->configRowIds = $configRowIds;
        $this->tag = $tag;
        $this->branchId = $branchId;
        $this->orchestrationJobId = $orchestrationJobId;
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
            'orchestrationJobId' => $this->orchestrationJobId,
            'configData' => $this->configData,
        ];
    }
}
