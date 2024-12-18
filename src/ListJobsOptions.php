<?php

declare(strict_types=1);

namespace Keboola\JobQueueClient;

use DateTimeInterface;
use Keboola\JobQueueClient\Exception\ClientException;

class ListJobsOptions
{
    private array $ids;
    private array $runIds;
    private array $branchIds;
    private array $tokenIds;
    private array $tokenDescriptions;
    private array $components;
    private array $configIds;
    private array $configRowIds;
    private array $modes;
    /**
     * @var array<JobStatuses>
     */
    private array $statuses;
    private DateTimeInterface $startTimeFrom;
    private DateTimeInterface $startTimeTo;
    private DateTimeInterface $createdTimeFrom;
    private DateTimeInterface $createdTimeTo;
    private DateTimeInterface $endTimeFrom;
    private DateTimeInterface $endTimeTo;
    private int $durationSecondsFrom;
    private int $durationSecondsTo;
    private int $offset = 0;
    private int $limit = 100;
    private string $sortBy;
    private string $sortOrder;
    private ?string $parentRunId = null;
    private JobType $type;

    /** @var string */
    public const SORT_ORDER_ASC = 'asc';

    /** @var string */
    public const SORT_ORDER_DESC = 'desc';

    public function getQueryParameters(): array
    {
        $arrayableProps = [
            'ids' => 'id',
            'runIds' => 'runId',
            'branchIds' => 'branchId',
            'tokenIds' => 'tokenId',
            'tokenDescriptions' => 'tokenDescription',
            'components' => 'component',
            'configIds' => 'configId',
            'configRowIds' => 'configRowIds',
            'modes' => 'mode',
        ];
        $scalarProps = [
            'durationSecondsFrom' => 'durationSecondsFrom',
            'durationSecondsTo' => 'durationSecondsTo',
            'offset' => 'offset',
            'limit' => 'limit',
            'sortBy' => 'sortBy',
            'sortOrder' => 'sortOrder',
        ];
        $enumProps = [
            'type' => 'type',
        ];
        $arrayableEnumProps = [
            'statuses' => 'status',
        ];
        $scalarPropsWithEmptyValueAllowed = [
            'parentRunId' => 'parentRunId',
        ];
        $dateTimeProps = [
            'startTimeFrom' => 'startTimeFrom',
            'startTimeTo' => 'startTimeTo',
            'createdTimeFrom' => 'createdTimeFrom',
            'createdTimeTo' => 'createdTimeTo',
            'endTimeFrom' => 'endTimeFrom',
            'endTimeTo' => 'endTimeTo',
        ];
        $parameters = [];
        foreach ($arrayableProps as $propName => $paramName) {
            if (!empty($this->$propName)) {
                foreach ($this->$propName as $value) {
                    $parameters[$paramName][] =  $value;
                }
            }
        }
        foreach ($scalarProps as $propName => $paramName) {
            if (!empty($this->$propName)) {
                $parameters[$paramName] =  $this->$propName;
            }
        }
        foreach ($enumProps as $propName => $paramName) {
            if (!empty($this->$propName)) {
                $parameters[$paramName] =  $this->$propName->value;
            }
        }
        foreach ($arrayableEnumProps as $propName => $paramName) {
            if (!empty($this->$propName)) {
                foreach ($this->$propName as $value) {
                    $parameters[$paramName][] =  $value->value;
                }
            }
        }
        foreach ($scalarPropsWithEmptyValueAllowed as $propName => $paramName) {
            if (isset($this->$propName)) {
                $parameters[$paramName] =  $this->$propName;
            }
        }
        foreach ($dateTimeProps as $propName => $paramName) {
            if (!empty($this->$propName)) {
                $parameters[$paramName] = $this->$propName->format('c');
            }
        }

        return $parameters;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function setIds(array $values): self
    {
        $this->ids = $values;
        return $this;
    }

    public function getRunIds(): array
    {
        return $this->runIds;
    }

    public function setRunIds(array $values): self
    {
        $this->runIds = $values;
        return $this;
    }

    public function getBranchIds(): array
    {
        return $this->branchIds;
    }

    public function setBranchIds(array $values): self
    {
        $this->branchIds = $values;
        return $this;
    }

    public function getTokenIds(): array
    {
        return $this->tokenIds;
    }

    public function setTokenIds(array $values): self
    {
        $this->tokenIds = $values;
        return $this;
    }

    public function getTokenDescriptions(): array
    {
        return $this->tokenDescriptions;
    }

    public function setTokenDescriptions(array $values): self
    {
        $this->tokenDescriptions = $values;
        return $this;
    }

    public function getComponents(): array
    {
        return $this->components;
    }

    public function setComponents(array $values): self
    {
        $this->components = $values;
        return $this;
    }

    public function getConfigIds(): array
    {
        return $this->configIds;
    }

    public function setConfigIds(array $values): self
    {
        $this->configIds = $values;
        return $this;
    }

    public function getConfigRowIds(): array
    {
        return $this->configRowIds;
    }

    public function setConfigRowIds(array $values): self
    {
        $this->configRowIds = $values;
        return $this;
    }

    public function getModes(): array
    {
        return $this->modes;
    }

    public function setModes(array $values): self
    {
        $this->modes = $values;
        return $this;
    }

    /**
     * @return JobStatuses[]
     */
    public function getStatuses(): array
    {
        return $this->statuses;
    }

    /**
     * @param JobStatuses[] $values
     * @return $this
     */
    public function setStatuses(array $values): self
    {
        $this->statuses = $values;
        return $this;
    }

    public function setDurationSecondsFrom(int $value): self
    {
        $this->durationSecondsFrom = $value;
        return $this;
    }

    public function getDurationSecondsFrom(): int
    {
        return $this->durationSecondsFrom;
    }

    public function setDurationSecondsTo(int $value): self
    {
        $this->durationSecondsTo = $value;
        return $this;
    }

    public function getDurationSecondsTo(): int
    {
        return $this->durationSecondsTo;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function setOffset(int $value): self
    {
        $this->offset = $value;
        return $this;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $value): self
    {
        $this->limit = $value;
        return $this;
    }

    public function getEndTimeFrom(): DateTimeInterface
    {
        return $this->endTimeFrom;
    }

    public function setEndTimeFrom(DateTimeInterface $value): self
    {
        $this->endTimeFrom = $value;
        return $this;
    }

    public function getEndTimeTo(): DateTimeInterface
    {
        return $this->endTimeTo;
    }

    public function setEndTimeTo(DateTimeInterface $value): self
    {
        $this->endTimeTo = $value;
        return $this;
    }

    public function getStartTimeFrom(): DateTimeInterface
    {
        return $this->startTimeFrom;
    }

    public function setStartTimeFrom(DateTimeInterface $value): self
    {
        $this->startTimeFrom = $value;
        return $this;
    }

    public function getStartTimeTo(): DateTimeInterface
    {
        return $this->startTimeTo;
    }

    public function setStartTimeTo(DateTimeInterface $value): self
    {
        $this->startTimeTo = $value;
        return $this;
    }

    public function getCreatedTimeFrom(): DateTimeInterface
    {
        return $this->createdTimeFrom;
    }

    public function setCreatedTimeFrom(DateTimeInterface $value): self
    {
        $this->createdTimeFrom = $value;
        return $this;
    }

    public function getCreatedTimeTo(): DateTimeInterface
    {
        return $this->createdTimeTo;
    }

    public function setCreatedTimeTo(DateTimeInterface $value): self
    {
        $this->createdTimeTo = $value;
        return $this;
    }

    public function getSortBy(): string
    {
        return $this->sortBy;
    }

    public function setSortBy(string $value): self
    {
        $this->sortBy = $value;
        return $this;
    }

    public function getSortOrder(): string
    {
        return $this->sortOrder;
    }

    public function setSortOrder(string $value): self
    {
        $allowedValues = [self::SORT_ORDER_ASC, self::SORT_ORDER_DESC];
        if (!in_array($value, $allowedValues)) {
            throw new ClientException(
                sprintf('Allowed values for "sortOrder" are [%s].', implode(', ', $allowedValues)),
            );
        }
        $this->sortOrder = $value;
        return $this;
    }

    public function getParentRunId(): ?string
    {
        return $this->parentRunId;
    }

    public function setParentRunId(?string $value): self
    {
        $this->parentRunId = $value;
        return $this;
    }

    public function getType(): JobType
    {
        return $this->type;
    }

    public function setType(JobType $type): self
    {
        $this->type = $type;
        return $this;
    }
}
