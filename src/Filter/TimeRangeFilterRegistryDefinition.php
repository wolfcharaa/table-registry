<?php

declare(strict_types=1);

namespace TableRegistry\Filter;

final class TimeRangeFilterRegistryDefinition extends BaseRegistryFilterDefinition
{
    public function __construct(
        string $queryKey,
        string $columnKey,
        string $title,
        private readonly string $fromKey,
        private readonly string $toKey
    ) {
        parent::__construct($queryKey, $title, $columnKey, 'time', ['from', 'to'], [
            'fromKey' => $fromKey,
            'toKey' => $toKey,
        ]);
    }

    public static function forColumn(string $columnKey, string $title): self
    {
        return new self($columnKey, $columnKey, $title, $columnKey . '_from', $columnKey . '_to');
    }

    public function fromKey(): string
    {
        return $this->fromKey;
    }

    public function toKey(): string
    {
        return $this->toKey;
    }
}
