<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Select\Definition;

use Wolfcharaa\TableRegistry\Exception\InvalidSelectDefinitionException;
use Wolfcharaa\TableRegistry\Select\Runtime\SelectRuntimeConstraintInterface;

/**
 * @api
 */
final class SelectDefinition
{
    /** @var non-empty-list<SelectFieldDefinition> */
    private array $fields;
    /** @var list<SelectCriterionDefinition> */
    private array $criteria = [];
    /** @var list<SelectSortDefinition> */
    private array $sorts = [];
    /** @var list<SelectOrderDefinition> */
    private array $order = [];
    /** @var list<callable|class-string<SelectRuntimeConstraintInterface>> */
    private array $runtimeConstraints = [];
    private ?SelectSearchDefinition $search = null;
    private ?SelectPaginationDefinition $pagination = null;
    private ?SelectCountDefinition $count = null;

    /**
     * @param array<array-key, mixed> $fields
     */
    private function __construct(private readonly SelectSourceDefinition $source, array $fields)
    {
        if ($fields === []) {
            throw InvalidSelectDefinitionException::because('Select definition must contain at least one field.');
        }

        $normalized = [];
        $aliases = [];
        foreach ($fields as $field) {
            if (!$field instanceof SelectFieldDefinition) {
                throw InvalidSelectDefinitionException::because(\sprintf(
                    'Select fields must be instances of %s.',
                    SelectFieldDefinition::class
                ));
            }

            $normalizedAlias = \strtolower($field->alias());
            if (isset($aliases[$normalizedAlias])) {
                throw InvalidSelectDefinitionException::because(\sprintf(
                    'Duplicate select field alias "%s".',
                    $field->alias()
                ));
            }

            $aliases[$normalizedAlias] = true;
            $normalized[] = $field;
        }

        /** @var non-empty-list<SelectFieldDefinition> $normalized */
        $this->fields = $normalized;
    }

    /**
     * @param array<array-key, mixed> $fields
     */
    public static function from(SelectSourceDefinition $source, array $fields): self
    {
        return new self($source, $fields);
    }

    public function withCriterion(SelectCriterionDefinition $criterion): self
    {
        foreach ($this->criteria as $existing) {
            if (\strcasecmp($existing->key(), $criterion->key()) === 0) {
                throw InvalidSelectDefinitionException::because(\sprintf(
                    'Duplicate select criterion key "%s".',
                    $criterion->key()
                ));
            }
        }

        $clone = clone $this;
        $clone->criteria[] = $criterion;

        return $clone;
    }

    public function withSearch(SelectSearchDefinition $search): self
    {
        $clone = clone $this;
        $clone->search = $search;

        return $clone;
    }

    public function withSort(SelectSortDefinition $sort): self
    {
        foreach ($this->sorts as $existing) {
            if (\strcasecmp($existing->key(), $sort->key()) === 0) {
                throw InvalidSelectDefinitionException::because(\sprintf(
                    'Duplicate select sort key "%s".',
                    $sort->key()
                ));
            }
        }

        $clone = clone $this;
        $clone->sorts[] = $sort;

        return $clone;
    }

    public function withOrderBy(SelectOrderDefinition $order): self
    {
        $clone = clone $this;
        $clone->order[] = $order;

        return $clone;
    }

    public function withPagination(SelectPaginationDefinition $pagination): self
    {
        if ($this->order === []) {
            throw InvalidSelectDefinitionException::because(
                'A paginated select definition must declare deterministic order before pagination.'
            );
        }

        $clone = clone $this;
        $clone->pagination = $pagination;

        return $clone;
    }

    public function withCount(SelectCountDefinition $count): self
    {
        $clone = clone $this;
        $clone->count = $count;

        return $clone;
    }

    /**
     * @param callable|class-string<SelectRuntimeConstraintInterface> $constraint
     */
    public function withRuntimeConstraint(callable|string $constraint): self
    {
        if (\is_string($constraint)) {
            if (\class_exists($constraint) && \is_subclass_of($constraint, SelectRuntimeConstraintInterface::class)) {
                // Valid class-string extension.
            } elseif (!\is_callable($constraint)) {
                throw InvalidSelectDefinitionException::because(\sprintf(
                    'Select runtime constraint "%s" must be callable or implement %s.',
                    $constraint,
                    SelectRuntimeConstraintInterface::class
                ));
            }
        }

        $clone = clone $this;
        $clone->runtimeConstraints[] = $constraint;

        return $clone;
    }

    public function source(): SelectSourceDefinition
    {
        return $this->source;
    }

    /** @return non-empty-list<SelectFieldDefinition> */
    public function fields(): array
    {
        return $this->fields;
    }

    public function field(string $alias): ?SelectFieldDefinition
    {
        foreach ($this->fields as $field) {
            if ($field->alias() === $alias) {
                return $field;
            }
        }

        return null;
    }

    /** @return list<SelectCriterionDefinition> */
    public function criteria(): array
    {
        return $this->criteria;
    }

    public function criterion(string $key): ?SelectCriterionDefinition
    {
        foreach ($this->criteria as $criterion) {
            if ($criterion->key() === $key) {
                return $criterion;
            }
        }

        return null;
    }

    public function search(): ?SelectSearchDefinition
    {
        return $this->search;
    }

    /** @return list<SelectSortDefinition> */
    public function sorts(): array
    {
        return $this->sorts;
    }

    public function sort(string $key): ?SelectSortDefinition
    {
        foreach ($this->sorts as $sort) {
            if ($sort->key() === $key) {
                return $sort;
            }
        }

        return null;
    }

    /** @return list<SelectOrderDefinition> */
    public function order(): array
    {
        return $this->order;
    }

    public function pagination(): ?SelectPaginationDefinition
    {
        return $this->pagination;
    }

    public function count(): ?SelectCountDefinition
    {
        return $this->count;
    }

    /** @return list<callable|class-string<SelectRuntimeConstraintInterface>> */
    public function runtimeConstraints(): array
    {
        return $this->runtimeConstraints;
    }
}
