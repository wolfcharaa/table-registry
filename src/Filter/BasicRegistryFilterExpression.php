<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Filter;

final class BasicRegistryFilterExpression implements RegistryFilterExpressionInterface
{
    public function __construct(
        private readonly ?string $columnKey,
        private readonly string $operator,
        private readonly mixed $value
    ) {
        if (\trim($operator) === '') {
            throw new \InvalidArgumentException('Filter operator must not be empty.');
        }
    }

    public function columnKey(): ?string
    {
        return $this->columnKey;
    }

    public function operator(): string
    {
        return $this->operator;
    }

    public function value(): mixed
    {
        return $this->value;
    }
}
