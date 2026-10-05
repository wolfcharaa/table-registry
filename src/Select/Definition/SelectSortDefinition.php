<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Definition;

use Wolfcharaa\TableRegistry\Exception\InvalidSelectDefinitionException;
use Wolfcharaa\TableRegistry\Internal\Select\Definition\Identifier;
use Wolfcharaa\TableRegistry\Select\Query\SelectSortDirection;

/**
 * @api
 */
final class SelectSortDefinition
{
    /** @var non-empty-list<SelectSortDirection> */
    private array $directions;

    /**
     * @param array<array-key, mixed> $directions
     */
    private function __construct(
        private readonly string $key,
        private readonly string $expression,
        array $directions,
        private ?string $nulls
    ) {
        Identifier::assertAlias($key, 'Select sort key');
        if (\trim($expression) === '') {
            throw InvalidSelectDefinitionException::because('Select sort expression must not be empty.');
        }

        $normalized = [];
        foreach ($directions as $direction) {
            if (!$direction instanceof SelectSortDirection) {
                throw InvalidSelectDefinitionException::because(\sprintf(
                    'Select sort directions must be instances of %s.',
                    SelectSortDirection::class
                ));
            }

            $normalized[$direction->value] = $direction;
        }

        if ($normalized === []) {
            throw InvalidSelectDefinitionException::because('Select sort must allow at least one direction.');
        }

        $this->directions = \array_values($normalized);
    }

    public static function both(string $key, string $expression): self
    {
        return new self(
            $key,
            $expression,
            [SelectSortDirection::Ascending, SelectSortDirection::Descending],
            null
        );
    }

    public static function ascending(string $key, string $expression): self
    {
        return new self($key, $expression, [SelectSortDirection::Ascending], null);
    }

    public static function descending(string $key, string $expression): self
    {
        return new self($key, $expression, [SelectSortDirection::Descending], null);
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

    public function key(): string
    {
        return $this->key;
    }

    public function expression(): string
    {
        return $this->expression;
    }

    /** @return non-empty-list<SelectSortDirection> */
    public function directions(): array
    {
        return $this->directions;
    }

    public function allows(SelectSortDirection $direction): bool
    {
        return \in_array($direction, $this->directions, true);
    }

    public function nulls(): ?string
    {
        return $this->nulls;
    }
}
