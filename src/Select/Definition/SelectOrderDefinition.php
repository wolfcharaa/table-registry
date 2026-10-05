<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Definition;

use Wolfcharaa\TableRegistry\Exception\InvalidSelectDefinitionException;
use Wolfcharaa\TableRegistry\Select\Query\SelectSortDirection;

/**
 * @api
 */
final class SelectOrderDefinition
{
    private function __construct(
        private readonly string $expression,
        private readonly SelectSortDirection $direction,
        private ?string $nulls
    ) {
        if (\trim($expression) === '') {
            throw InvalidSelectDefinitionException::because('Select order expression must not be empty.');
        }
    }

    public static function ascending(string $expression): self
    {
        return new self($expression, SelectSortDirection::Ascending, null);
    }

    public static function descending(string $expression): self
    {
        return new self($expression, SelectSortDirection::Descending, null);
    }

    public function withNullsFirst(): self
    {
        $clone = clone $this;
        $clone->nulls = 'first';

        return $clone;
    }

    public function withNullsLast(): self
    {
        $clone = clone $this;
        $clone->nulls = 'last';

        return $clone;
    }

    public function expression(): string
    {
        return $this->expression;
    }

    public function direction(): SelectSortDirection
    {
        return $this->direction;
    }

    public function nulls(): ?string
    {
        return $this->nulls;
    }
}
