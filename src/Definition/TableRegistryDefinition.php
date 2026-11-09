<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Definition;

use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinitionCollection;
use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinitionValidator;
use Wolfcharaa\TableRegistry\Exception\InvalidRegistryDefinitionException;
use Wolfcharaa\TableRegistry\Query\RegistryPaginationDefinition;
use Wolfcharaa\TableRegistry\Row\RegistryRowInputDefinition;
use Wolfcharaa\TableRegistry\Search\RegistrySearchDefinitionInterface;
use Wolfcharaa\TableRegistry\Search\SearchBySearchableColumnsDefinition;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlRuntimeConstraintInterface;
use Wolfcharaa\TableRegistry\Sort\RegistryDefaultSortDefinition;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;

/**
 * @api
 */
class TableRegistryDefinition implements \JsonSerializable
{
    /** @var list<ColumnRegistryDefinition> */
    private array $columns;
    /** @var list<callable|class-string<RegistrySqlRuntimeConstraintInterface>> */
    private array $runtimeConstraints = [];
    /** @var list<RegistryRowInputDefinition> */
    private array $rowInputs = [];
    private RegistrySearchDefinitionInterface $search;
    private RegistryPaginationDefinition $pagination;
    private ?RegistryDefaultSortDefinition $defaultSort = null;

    /**
     * @param array<array-key, mixed> $columns
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

    public function withRowInput(RegistryRowInputDefinition $input): static
    {
        if ($this->source->isSql() && $input->select() === null) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'SQL registry row input "%s" must define a select.',
                $input->key()
            ));
        }

        if ($this->source->isExternal() && $input->select() !== null) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'External registry row input "%s" cannot define a SQL select.',
                $input->key()
            ));
        }

        foreach ($this->occupiedRowKeys() as $key) {
            if ($key === $input->key()) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Duplicate registry raw row key "%s".',
                    $input->key()
                ));
            }
        }

        $clone = clone $this;
        $clone->rowInputs[] = $input;

        return $clone;
    }

    /**
     * @return list<RegistryRowInputDefinition>
     */
    public function rowInputs(): array
    {
        return $this->rowInputs;
    }

    /**
     * @return array{
     *     schemaVersion:int,
     *     registryKey:string,
     *     title:string,
     *     primaryKey:string,
     *     columns:list<array<string, mixed>>,
     *     query:array{
     *         search:mixed,
     *         pagination:array<string, mixed>,
     *         defaultSort?:array<string, mixed>
     *     }
     * }
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
                $columns->exposedInDefinition()
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

    /**
     * @return list<string>
     */
    private function occupiedRowKeys(): array
    {
        $keys = [];

        foreach ($this->columns as $column) {
            $keys[] = $column->key();

            foreach ($column->auxiliarySelects() as $select) {
                $keys[] = $select->alias();
            }
        }

        foreach ($this->rowInputs as $input) {
            $keys[] = $input->key();
        }

        return $keys;
    }
}
