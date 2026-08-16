<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Sort;

final class RegistryDefaultSortDefinition implements \JsonSerializable
{
    public function __construct(
        private readonly string $columnKey,
        private readonly string $direction = 'asc'
    ) {
        if (\trim($columnKey) === '') {
            throw new \InvalidArgumentException('Default sort column key must not be empty.');
        }

        if (!\in_array(\strtolower($direction), ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('Default sort direction must be asc or desc.');
        }
    }

    public static function asc(string $columnKey): self
    {
        return new self($columnKey, 'asc');
    }

    public static function desc(string $columnKey): self
    {
        return new self($columnKey, 'desc');
    }

    public function columnKey(): string
    {
        return $this->columnKey;
    }

    public function direction(): string
    {
        return \strtolower($this->direction);
    }

    /**
     * @return array{column:string,direction:string}
     */
    public function jsonSerialize(): array
    {
        return [
            'column' => $this->columnKey,
            'direction' => $this->direction(),
        ];
    }
}
