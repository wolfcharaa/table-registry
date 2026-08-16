<?php

declare(strict_types=1);

namespace TableRegistry\Definition;

use TableRegistry\Column\ColumnRegistryDefinition;
use TableRegistry\Column\ColumnRegistryDefinitionCollection;
use TableRegistry\Column\ColumnRegistryDefinitionValidator;
use TableRegistry\Exception\InvalidRegistryDefinitionException;
use TableRegistry\Query\RegistryPaginationDefinition;
use TableRegistry\Search\RegistrySearchDefinitionInterface;
use TableRegistry\Search\SearchBySearchableColumnsDefinition;
use TableRegistry\Sql\RegistrySqlRuntimeConstraintInterface;
use TableRegistry\Sort\RegistryDefaultSortDefinition;
use TableRegistry\Source\RegistrySourceDefinition;

class TableRegistryDefinition implements \JsonSerializable
{
    /** @var list<ColumnRegistryDefinition> */
    private array $columns;
    /** @var list<callable|class-string<RegistrySqlRuntimeConstraintInterface>> */
    private array $runtimeConstraints = [];
    private RegistrySearchDefinitionInterface $search;
    private RegistryPaginationDefinition $pagination;
    private ?RegistryDefaultSortDefinition $defaultSort = null;

    /**
     * @param list<ColumnRegistryDefinition> $columns
     */
    public function __construct(
        private readonly string $registryKey,
        private readonly string $title,
        private readonly RegistrySourceDefinition $source,
        array $columns
    ) {
        if (\trim($registryKey) === '') {
            throw new \InvalidArgumentException('Registry key must not be empty.');
        }

        if (\trim($title) === '') {
            throw new \InvalidArgumentException('Registry title must not be empty.');
        }

        ColumnRegistryDefinitionValidator::assertValid($columns);

        $this->columns = \array_values($columns);
        $this->search = SearchBySearchableColumnsDefinition::default();
        $this->pagination = RegistryPaginationDefinition::page();
    }

    public function registryKey(): string
    {
        return $this->registryKey;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function source(): RegistrySourceDefinition
    {
        return $this->source;
    }

    public function columns(): ColumnRegistryDefinitionCollection
    {
        return new ColumnRegistryDefinitionCollection($this->columns);
    }

    public function withSearch(RegistrySearchDefinitionInterface $search): static
    {
        $clone = clone $this;
        $clone->search = $search;

        return $clone;
    }

    public function search(): RegistrySearchDefinitionInterface
    {
        return $this->search;
    }

    public function withPagination(RegistryPaginationDefinition $pagination): static
    {
        $clone = clone $this;
        $clone->pagination = $pagination;

        return $clone;
    }

    public function pagination(): RegistryPaginationDefinition
    {
        return $this->pagination;
    }

    public function withDefaultSort(?RegistryDefaultSortDefinition $defaultSort): static
    {
        if ($defaultSort !== null && $this->columns()->get($defaultSort->columnKey()) === null) {
            throw new \InvalidArgumentException(\sprintf(
                'Default sort column "%s" does not exist in registry "%s".',
                $defaultSort->columnKey(),
                $this->registryKey
            ));
        }

        $clone = clone $this;
        $clone->defaultSort = $defaultSort;

        return $clone;
    }

    public function defaultSort(): ?RegistryDefaultSortDefinition
    {
        return $this->defaultSort;
    }

    /**
     * @param callable|class-string<RegistrySqlRuntimeConstraintInterface> $resolver
     */
    public function withRuntimeConstraint(callable|string $resolver): static
    {
        $this->assertRuntimeConstraint($resolver);

        $clone = clone $this;
        $clone->runtimeConstraints[] = $resolver;

        return $clone;
    }

    /**
     * @return list<callable|class-string<RegistrySqlRuntimeConstraintInterface>>
     */
    public function runtimeConstraints(): array
    {
        return $this->runtimeConstraints;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $columns = $this->columns();

        $payload = [
            'schemaVersion' => 2,
            'registryKey' => $this->registryKey,
            'title' => $this->title,
            'primaryKey' => $columns->primaryKey()->key(),
            'columns' => \array_map(
                static fn (ColumnRegistryDefinition $column): array => $column->jsonSerialize(),
                $columns->all()
            ),
            'query' => [
                'search' => $this->search->jsonSerialize(),
                'pagination' => $this->pagination->jsonSerialize(),
            ],
        ];

        if ($this->defaultSort !== null) {
            $payload['query']['defaultSort'] = $this->defaultSort->jsonSerialize();
        }

        return $payload;
    }

    private function assertRuntimeConstraint(callable|string $resolver): void
    {
        if (!\is_string($resolver)) {
            return;
        }

        if (!\class_exists($resolver)) {
            if (\is_callable($resolver)) {
                return;
            }

            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Registry runtime constraint "%s" must be callable or existing class.',
                $resolver
            ));
        }

        if (!\is_subclass_of($resolver, RegistrySqlRuntimeConstraintInterface::class)) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Registry runtime constraint "%s" must implement %s.',
                $resolver,
                RegistrySqlRuntimeConstraintInterface::class
            ));
        }
    }
}
