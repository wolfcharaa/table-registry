<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Column;

/**
 * @api
 */
final class ColumnSelectDefinition
{
    private function __construct(
        private readonly string $alias,
        private readonly string $expression
    ) {
        if (\trim($alias) === '' || \trim($expression) === '') {
            throw new \InvalidArgumentException('Column select alias and expression must not be empty.');
        }
    }

    public static function column(string $column, ?string $alias = null): self
    {
        return new self($alias ?? $column, $column);
    }

    public static function sqlExpression(string $alias, string $expression): self
    {
        return new self($alias, $expression);
    }

    public function alias(): string
    {
        return $this->alias;
    }

    public function expression(): string
    {
        return $this->expression;
    }
}
