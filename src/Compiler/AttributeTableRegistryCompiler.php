<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Compiler;

use Wolfcharaa\TableRegistry\Attribute\AsyncSelectFilter;
use Wolfcharaa\TableRegistry\Attribute\BooleanFilter;
use Wolfcharaa\TableRegistry\Attribute\Column;
use Wolfcharaa\TableRegistry\Attribute\ColumnMode;
use Wolfcharaa\TableRegistry\Attribute\CountSource;
use Wolfcharaa\TableRegistry\Attribute\DateRangeFilter;
use Wolfcharaa\TableRegistry\Attribute\DefaultSort;
use Wolfcharaa\TableRegistry\Attribute\ExternalRows;
use Wolfcharaa\TableRegistry\Attribute\From;
use Wolfcharaa\TableRegistry\Attribute\IntegerFilter;
use Wolfcharaa\TableRegistry\Attribute\Join;
use Wolfcharaa\TableRegistry\Attribute\Label;
use Wolfcharaa\TableRegistry\Attribute\NullabilityFilter;
use Wolfcharaa\TableRegistry\Attribute\Pagination;
use Wolfcharaa\TableRegistry\Attribute\PreloadSelectFilter;
use Wolfcharaa\TableRegistry\Attribute\PrimaryKey;
use Wolfcharaa\TableRegistry\Attribute\Project;
use Wolfcharaa\TableRegistry\Attribute\RawType;
use Wolfcharaa\TableRegistry\Attribute\Search;
use Wolfcharaa\TableRegistry\Attribute\Searchable;
use Wolfcharaa\TableRegistry\Attribute\Sort;
use Wolfcharaa\TableRegistry\Attribute\SqlExpression;
use Wolfcharaa\TableRegistry\Attribute\SqlExpressionProvider;
use Wolfcharaa\TableRegistry\Attribute\StaticSelectFilter;
use Wolfcharaa\TableRegistry\Attribute\Table;
use Wolfcharaa\TableRegistry\Attribute\TextFilter;
use Wolfcharaa\TableRegistry\Attribute\TextFilterOperator;
use Wolfcharaa\TableRegistry\Attribute\TimeRangeFilter;
use Wolfcharaa\TableRegistry\Attribute\Variant;
use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\ColumnSelectDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Exception\AttributeCompilationException;
use Wolfcharaa\TableRegistry\Exception\InvalidRegistryDefinitionException;
use Wolfcharaa\TableRegistry\Filter\BooleanFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\DateRangeFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\IntegerFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\NullabilityFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\RegistryFilterDefinitionInterface;
use Wolfcharaa\TableRegistry\Filter\SelectFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\TextFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\TimeRangeFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Internal\Attribute\AttributeReader;
use Wolfcharaa\TableRegistry\Internal\Attribute\FieldTypeResolver;
use Wolfcharaa\TableRegistry\Internal\Compiler\SqlTopologyRenderer;
use Wolfcharaa\TableRegistry\Internal\Sql\Identifier;
use Wolfcharaa\TableRegistry\Metadata\AttributeRelationMetadataProvider;
use Wolfcharaa\TableRegistry\Metadata\RelationMetadata;
use Wolfcharaa\TableRegistry\Metadata\RelationMetadataProviderInterface;
use Wolfcharaa\TableRegistry\Query\RegistryPaginationDefinition;
use Wolfcharaa\TableRegistry\Search\SearchBySearchableColumnsDefinition;
use Wolfcharaa\TableRegistry\Sort\RegistryDefaultSortDefinition;
use Wolfcharaa\TableRegistry\Sort\RegistrySortDefinition;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;

/**
 * @api
 */
final class AttributeTableRegistryCompiler
{
    private readonly AttributeReader $attributes;
    private readonly FieldTypeResolver $types;
    private readonly SqlTopologyRenderer $sql;

    public function __construct(
        private readonly RelationMetadataProviderInterface $relations = new AttributeRelationMetadataProvider(),
        private readonly CompileTimeProviderResolverInterface $providers = new NativeCompileTimeProviderResolver()
    ) {
        $this->attributes = new AttributeReader();
        $this->types = new FieldTypeResolver();
        $this->sql = new SqlTopologyRenderer();
    }

    /**
     * @param class-string $aggregate
     */
    public function compile(string $aggregate, ?string $variant = null): TableRegistryDefinition
    {
        $providerCache = [];
        $relationCache = [];

        return $this->compileOne($aggregate, $variant, $providerCache, $relationCache);
    }

    /**
     * @param iterable<class-string> $aggregates
     */
    public function compileAll(iterable $aggregates): CompiledTableDefinitionCollection
    {
        $definitions = [];
        $issues = [];
        $providerCache = [];
        $relationCache = [];

        foreach ($aggregates as $aggregate) {
            try {
                $definition = $this->compileOne($aggregate, null, $providerCache, $relationCache);
                $key = $definition->registryKey();
                if (isset($definitions[$key])) {
                    $issues[] = new AttributeCompilationIssue(
                        'catalog.duplicate_key',
                        \sprintf('Registry key "%s" is already compiled.', $key),
                        $aggregate
                    );
                    continue;
                }

                $definitions[$key] = $definition;
            } catch (AttributeCompilationException $exception) {
                $issues = [...$issues, ...$exception->issues()];
            }
        }

        if ($issues !== []) {
            throw new AttributeCompilationException($issues);
        }

        return new CompiledTableDefinitionCollection($definitions);
    }

    /**
     * @param class-string $aggregate
     */
    public function compileVariants(string $aggregate): CompiledTableDefinitionCollection
    {
        return new CompiledTableDefinitionCollection([
            'default' => $this->compile($aggregate),
        ]);
    }

    /**
     * @param class-string $aggregate
     * @param array<class-string, object> $providerCache
     * @param array<class-string, RelationMetadata> $relationCache
     */
    private function compileOne(
        string $aggregate,
        ?string $variant,
        array &$providerCache,
        array &$relationCache
    ): TableRegistryDefinition {
        try {
            return $this->doCompile($aggregate, $variant, $providerCache, $relationCache);
        } catch (AttributeCompilationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new AttributeCompilationException([
                new AttributeCompilationIssue(
                    'definition.invalid',
                    $exception->getMessage(),
                    $aggregate,
                    $variant
                ),
            ]);
        }
    }

    /**
     * @param class-string $aggregate
     * @param array<class-string, object> $providerCache
     * @param array<class-string, RelationMetadata> $relationCache
     */
    private function doCompile(
        string $aggregate,
        ?string $variant,
        array &$providerCache,
        array &$relationCache
    ): TableRegistryDefinition {
        if (!\class_exists($aggregate)) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Aggregate "%s" must be an existing class.',
                $aggregate
            ));
        }

        $reflection = new \ReflectionClass($aggregate);
        $tables = $this->attributes->instances($reflection, Table::class);
        $variants = $this->attributes->instances($reflection, Variant::class);
        if ($variant !== null || $variants !== []) {
            throw InvalidRegistryDefinitionException::because('Named aggregate variants are introduced in compiler Slice 5.');
        }

        if (\count($tables) !== 1 || !$tables[0] instanceof Table) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Aggregate "%s" must declare exactly one Table Attribute.',
                $aggregate
            ));
        }
        $table = $tables[0];

        if ($this->attributes->has($reflection, ExternalRows::class)) {
            throw InvalidRegistryDefinitionException::because(
                'ExternalRows aggregates are introduced in compiler Slice 4.'
            );
        }

        $from = $this->singleAttribute($reflection, From::class, 'source');
        if (!$from instanceof From) {
            throw InvalidRegistryDefinitionException::because('SQL aggregate must declare From.');
        }
        $this->assertAlias($from->alias, 'root participant');

        $root = $this->relation($from->model, $providerCache, $relationCache);
        $participants = [$from->alias => $root];
        $joins = $this->joins($reflection);

        foreach ($joins as $join) {
            $this->assertAlias($join->alias, 'JOIN participant');
            if (isset($participants[$join->alias])) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Duplicate participant alias "%s".',
                    $join->alias
                ));
            }

            $relation = $this->relation($join->model, $providerCache, $relationCache);
            if ($relation->source()->connection() !== $root->source()->connection()) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Participant "%s" uses connection "%s" instead of "%s".',
                    $join->alias,
                    $relation->source()->connection(),
                    $root->source()->connection()
                ));
            }

            $participants[$join->alias] = $relation;
        }

        $source = RegistrySourceDefinition::from($this->sql->source($root, $from->alias))
            ->withConnection((string)$root->source()->connection());
        foreach ($joins as $join) {
            $source = $source->withJoin($this->sql->join($join, $participants[$join->alias], $participants));
        }

        $countSource = $this->optionalAttribute($reflection, CountSource::class);
        if ($countSource instanceof CountSource) {
            $source = $source->withCountSource($countSource->source);
        }

        $columns = $this->columns($reflection, $participants, $providerCache);
        $definition = new TableRegistryDefinition($table->key, $table->title, $source, $columns);

        $search = $this->optionalAttribute($reflection, Search::class);
        if ($search instanceof Search) {
            $definition = $definition->withSearch(new SearchBySearchableColumnsDefinition(
                $search->queryKey,
                $search->enabled
            ));
        }

        $pagination = $this->optionalAttribute($reflection, Pagination::class);
        if ($pagination instanceof Pagination) {
            $definition = $definition->withPagination(new RegistryPaginationDefinition(
                $pagination->pageKey,
                $pagination->perPageKey,
                $pagination->defaultPerPage,
                $pagination->maxPerPage
            ));
        }

        $defaultSort = $this->optionalAttribute($reflection, DefaultSort::class);
        if ($defaultSort instanceof DefaultSort) {
            $definition = $definition->withDefaultSort(new RegistryDefaultSortDefinition(
                $defaultSort->column,
                $defaultSort->direction
            ));
        }

        return $definition;
    }

    /**
     * @param \ReflectionClass<object> $aggregate
     * @return list<Join>
     */
    private function joins(\ReflectionClass $aggregate): array
    {
        $joins = [];
        $positions = [];

        foreach ($this->attributes->instances($aggregate, Join::class) as $attribute) {
            if (!$attribute instanceof Join) {
                continue;
            }

            if ($attribute->position < 1) {
                throw InvalidRegistryDefinitionException::because('JOIN position must be positive.');
            }
            if (isset($positions[$attribute->position])) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Duplicate JOIN position %d.',
                    $attribute->position
                ));
            }

            $positions[$attribute->position] = true;
            $joins[] = $attribute;
        }

        \usort($joins, static fn (Join $left, Join $right): int => $left->position <=> $right->position);

        return $joins;
    }

    /**
     * @param \ReflectionClass<object> $aggregate
     * @param array<string, RelationMetadata> $participants
     * @param array<class-string, object> $providerCache
     * @return list<ColumnRegistryDefinition>
     */
    private function columns(\ReflectionClass $aggregate, array $participants, array &$providerCache): array
    {
        $columns = [];
        $positions = [];
        $keys = [];

        foreach ($aggregate->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() !== $aggregate->getName()) {
                continue;
            }

            $column = $this->attributes->first($property, Column::class);
            if (!$column instanceof Column) {
                continue;
            }
            if ($property->isStatic()) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Aggregate column %s::$%s must not be static.',
                    $aggregate->getName(),
                    $property->getName()
                ));
            }
            if ($column->position < 1 || isset($positions[$column->position])) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Aggregate column %s::$%s must have a unique positive position.',
                    $aggregate->getName(),
                    $property->getName()
                ));
            }

            $key = $column->key ?? $property->getName();
            $this->assertAlias($key, 'column key');
            if (isset($keys[$key])) {
                throw InvalidRegistryDefinitionException::because(\sprintf('Duplicate aggregate column key "%s".', $key));
            }

            $positions[$column->position] = true;
            $keys[$key] = true;
            $columns[] = [
                'position' => $column->position,
                'definition' => $this->column($property, $column, $key, $participants, $providerCache),
            ];
        }

        \usort($columns, static fn (array $left, array $right): int => $left['position'] <=> $right['position']);

        return \array_map(
            static fn (array $column): ColumnRegistryDefinition => $column['definition'],
            $columns
        );
    }

    /**
     * @param array<string, RelationMetadata> $participants
     * @param array<class-string, object> $providerCache
     */
    private function column(
        \ReflectionProperty $property,
        Column $metadata,
        string $key,
        array $participants,
        array &$providerCache
    ): ColumnRegistryDefinition {
        $primary = $this->attributes->has($property, PrimaryKey::class);
        $column = $primary
            ? new PrimaryKeyColumnRegistryDefinition($key, $metadata->title)
            : new ColumnRegistryDefinition($key, $metadata->title);

        [$select, $rowType] = $this->columnInput($property, $key, $participants, $providerCache);
        if ($select === null && $metadata->mode !== ColumnMode::Output) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Aggregate property %s::$%s must declare a SQL input.',
                $property->getDeclaringClass()->getName(),
                $property->getName()
            ));
        }
        if ($select !== null && $metadata->mode === ColumnMode::Output) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Output-only aggregate property %s::$%s cannot declare a SQL input.',
                $property->getDeclaringClass()->getName(),
                $property->getName()
            ));
        }
        if ($select !== null) {
            $column = $column->withSelect($select)->withRowTypeObject($rowType);
        }

        if ($metadata->mode === ColumnMode::Payload) {
            $column = $column->withPayloadOnly();
        } elseif ($metadata->mode === ColumnMode::Output) {
            $column = $column->withOutputOnly();
        }

        if ($metadata->exportable !== null) {
            $column = $column->withExportable($metadata->exportable);
        }
        if ($metadata->hint !== null) {
            $column = $column->withHint($metadata->hint);
        }
        if ($this->attributes->has($property, Searchable::class)) {
            $column = $column->withSearchable();
        }

        $sort = $this->attributes->first($property, Sort::class);
        if ($sort instanceof Sort) {
            $column = $column->withSort($sort->expression === null
                ? RegistrySortDefinition::byColumn($key)
                : RegistrySortDefinition::byExpression($key, $sort->expression));
        }

        $label = $this->attributes->first($property, Label::class);
        if ($label instanceof Label) {
            $column = $column->withLabel($label->text);
        }

        $filter = $this->filter($property, $key, $metadata->title);
        if ($filter !== null) {
            $column = $column->withFilter($filter);
        }

        return $column;
    }

    /**
     * @param array<string, RelationMetadata> $participants
     * @param array<class-string, object> $providerCache
     * @return array{0:?ColumnSelectDefinition,1:class-string<\Wolfcharaa\TableRegistry\Value\PrimitiveValueObjectInterface>}
     */
    private function columnInput(
        \ReflectionProperty $property,
        string $key,
        array $participants,
        array &$providerCache
    ): array {
        $project = $this->attributes->first($property, Project::class);
        $expression = $this->attributes->first($property, SqlExpression::class);
        $expressionProvider = $this->attributes->first($property, SqlExpressionProvider::class);
        $inputs = \array_values(\array_filter([$project, $expression, $expressionProvider]));

        if ($inputs === []) {
            return [null, \Wolfcharaa\TableRegistry\Value\RawValue::class];
        }
        if (\count($inputs) !== 1) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Aggregate property %s::$%s must declare exactly one SQL input.',
                $property->getDeclaringClass()->getName(),
                $property->getName()
            ));
        }

        if ($project instanceof Project) {
            $relation = $participants[$project->alias] ?? null;
            if ($relation === null) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Aggregate property %s::$%s references unknown participant "%s".',
                    $property->getDeclaringClass()->getName(),
                    $property->getName(),
                    $project->alias
                ));
            }

            $fieldName = $project->field ?? $property->getName();
            $field = $relation->field($fieldName);
            if ($field === null) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Aggregate property %s::$%s references unknown field "%s.%s".',
                    $property->getDeclaringClass()->getName(),
                    $property->getName(),
                    $project->alias,
                    $fieldName
                ));
            }

            return [
                ColumnSelectDefinition::sqlExpression(
                    $key,
                    $this->sql->field($relation, $project->alias, $fieldName)
                ),
                $field->rowType(),
            ];
        }

        $sql = $expression instanceof SqlExpression ? $expression->expression : null;
        if ($expressionProvider instanceof SqlExpressionProvider) {
            $provider = $this->provider($expressionProvider->provider, $providerCache);
            if (!$provider instanceof SqlExpressionProviderInterface) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'SQL expression provider "%s" must implement %s.',
                    $expressionProvider->provider,
                    SqlExpressionProviderInterface::class
                ));
            }
            $sql = $provider();
        }
        if ($sql === null || \trim($sql) === '') {
            throw InvalidRegistryDefinitionException::because('SQL expression must not be empty.');
        }

        $rawType = $this->attributes->first($property, RawType::class);
        $field = $rawType instanceof RawType
            ? new \Wolfcharaa\TableRegistry\Attribute\Field(rowType: $rawType->rowType, nullable: $rawType->nullable)
            : new \Wolfcharaa\TableRegistry\Attribute\Field();
        $resolved = $this->types->resolve($property, $field);

        return [ColumnSelectDefinition::sqlExpression($key, $sql), $resolved['rowType']];
    }

    private function filter(
        \ReflectionProperty $property,
        string $key,
        string $title
    ): ?RegistryFilterDefinitionInterface {
        $filters = [];
        foreach ([
            TextFilter::class,
            IntegerFilter::class,
            BooleanFilter::class,
            NullabilityFilter::class,
            DateRangeFilter::class,
            TimeRangeFilter::class,
            StaticSelectFilter::class,
            PreloadSelectFilter::class,
            AsyncSelectFilter::class,
        ] as $attribute) {
            $candidate = $this->attributes->first($property, $attribute);
            if ($candidate !== null) {
                $filters[] = $candidate;
            }
        }

        if (\count($filters) > 1) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Aggregate property %s::$%s declares more than one filter.',
                $property->getDeclaringClass()->getName(),
                $property->getName()
            ));
        }
        if ($filters === []) {
            return null;
        }

        $filter = $filters[0];
        if ($filter instanceof TextFilter) {
            return $filter->operator === TextFilterOperator::Contains
                ? TextFilterRegistryDefinition::contains($filter->queryKey ?? $key, $key, $title)
                : TextFilterRegistryDefinition::exact($filter->queryKey ?? $key, $key, $title);
        }
        if ($filter instanceof IntegerFilter) {
            return IntegerFilterRegistryDefinition::exact($filter->queryKey ?? $key, $key, $title);
        }
        if ($filter instanceof BooleanFilter) {
            return BooleanFilterRegistryDefinition::exact($filter->queryKey ?? $key, $key, $title);
        }
        if ($filter instanceof NullabilityFilter) {
            return NullabilityFilterRegistryDefinition::forColumn($filter->queryKey ?? $key, $key, $title);
        }
        if ($filter instanceof DateRangeFilter) {
            return new DateRangeFilterRegistryDefinition(
                $filter->queryKey ?? $key,
                $key,
                $title,
                $filter->fromKey ?? $key . '_from',
                $filter->toKey ?? $key . '_to'
            );
        }
        if ($filter instanceof TimeRangeFilter) {
            return new TimeRangeFilterRegistryDefinition(
                $filter->queryKey ?? $key,
                $key,
                $title,
                $filter->fromKey ?? $key . '_from',
                $filter->toKey ?? $key . '_to'
            );
        }
        if ($filter instanceof StaticSelectFilter) {
            return SelectFilterRegistryDefinition::static(
                $filter->queryKey ?? $key,
                $key,
                $title,
                $filter->options,
                $filter->valueFormat
            );
        }
        if ($filter instanceof PreloadSelectFilter) {
            return SelectFilterRegistryDefinition::preload(
                $filter->queryKey ?? $key,
                $key,
                $title,
                $filter->route,
                $filter->valueFormat
            );
        }
        if ($filter instanceof AsyncSelectFilter) {
            return SelectFilterRegistryDefinition::async(
                $filter->queryKey ?? $key,
                $key,
                $title,
                $filter->route,
                $filter->valueFormat
            );
        }

        throw new \LogicException('Unsupported filter Attribute.');
    }

    /**
     * @param class-string $model
     * @param array<class-string, object> $providerCache
     * @param array<class-string, RelationMetadata> $relationCache
     */
    private function relation(string $model, array &$providerCache, array &$relationCache): RelationMetadata
    {
        if (isset($relationCache[$model])) {
            return $relationCache[$model];
        }

        $metadata = $this->relations->metadata($model);
        if ($metadata === null) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Participant model "%s" does not provide relation metadata.',
                $model
            ));
        }

        $source = $metadata->source();
        if ($source->isProvided()) {
            $providerClass = $source->provider();
            if ($providerClass === null) {
                throw new \LogicException('Provider-backed source has no provider class.');
            }
            $provider = $this->provider($providerClass, $providerCache);
            if (!$provider instanceof RelationSourceProviderInterface) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Relation source provider "%s" must implement %s.',
                    $providerClass,
                    RelationSourceProviderInterface::class
                ));
            }
            $source = $provider();
            if ($source->isProvided()) {
                throw InvalidRegistryDefinitionException::because('Relation source provider must return a resolved source.');
            }
            $metadata = new RelationMetadata($source, $metadata->fields());
        }

        return $relationCache[$model] = $metadata;
    }

    /**
     * @param class-string $provider
     * @param array<class-string, object> $providerCache
     */
    private function provider(string $provider, array &$providerCache): object
    {
        return $providerCache[$provider] ??= $this->providers->resolve($provider);
    }

    /**
     * @param \ReflectionClass<object> $reflection
     * @param class-string $attribute
     */
    private function singleAttribute(\ReflectionClass $reflection, string $attribute, string $subject): object
    {
        $attributes = $this->attributes->instances($reflection, $attribute);
        if (\count($attributes) !== 1) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Aggregate %s must declare exactly one %s Attribute.',
                $subject,
                $attribute
            ));
        }

        return $attributes[0];
    }

    /**
     * @param \ReflectionClass<object> $reflection
     * @param class-string $attribute
     */
    private function optionalAttribute(\ReflectionClass $reflection, string $attribute): ?object
    {
        $attributes = $this->attributes->instances($reflection, $attribute);
        if (\count($attributes) > 1) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Aggregate must not declare multiple common %s Attributes.',
                $attribute
            ));
        }

        return $attributes[0] ?? null;
    }

    private function assertAlias(string $alias, string $subject): void
    {
        if (!Identifier::isAlias($alias)) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                '%s "%s" must match [A-Za-z_][A-Za-z0-9_]*.',
                $subject,
                $alias
            ));
        }
    }
}
