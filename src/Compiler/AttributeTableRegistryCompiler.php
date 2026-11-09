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
use Wolfcharaa\TableRegistry\Attribute\ExternalField;
use Wolfcharaa\TableRegistry\Attribute\ExternalRows;
use Wolfcharaa\TableRegistry\Attribute\Field;
use Wolfcharaa\TableRegistry\Attribute\From;
use Wolfcharaa\TableRegistry\Attribute\HydrateWith;
use Wolfcharaa\TableRegistry\Attribute\IntegerFilter;
use Wolfcharaa\TableRegistry\Attribute\Internal;
use Wolfcharaa\TableRegistry\Attribute\Join;
use Wolfcharaa\TableRegistry\Attribute\Label;
use Wolfcharaa\TableRegistry\Attribute\LabelFrom;
use Wolfcharaa\TableRegistry\Attribute\LabelWith;
use Wolfcharaa\TableRegistry\Attribute\NullabilityFilter;
use Wolfcharaa\TableRegistry\Attribute\Pagination;
use Wolfcharaa\TableRegistry\Attribute\PreloadSelectFilter;
use Wolfcharaa\TableRegistry\Attribute\PrimaryKey;
use Wolfcharaa\TableRegistry\Attribute\Project;
use Wolfcharaa\TableRegistry\Attribute\RawType;
use Wolfcharaa\TableRegistry\Attribute\RuntimeConstraint;
use Wolfcharaa\TableRegistry\Attribute\Search;
use Wolfcharaa\TableRegistry\Attribute\Searchable;
use Wolfcharaa\TableRegistry\Attribute\Sort;
use Wolfcharaa\TableRegistry\Attribute\SqlConstraint;
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
use Wolfcharaa\TableRegistry\Column\ColumnValueHydratorInterface;
use Wolfcharaa\TableRegistry\Column\ColumnValueLabelResolverInterface;
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
use Wolfcharaa\TableRegistry\Internal\Runtime\LabelFromRowValue;
use Wolfcharaa\TableRegistry\Internal\Runtime\StaticRegistrySqlConstraint;
use Wolfcharaa\TableRegistry\Internal\Sql\Identifier;
use Wolfcharaa\TableRegistry\Metadata\AttributeRelationMetadataProvider;
use Wolfcharaa\TableRegistry\Metadata\RelationMetadata;
use Wolfcharaa\TableRegistry\Metadata\RelationMetadataProviderInterface;
use Wolfcharaa\TableRegistry\Query\RegistryPaginationDefinition;
use Wolfcharaa\TableRegistry\Row\RegistryRowInputDefinition;
use Wolfcharaa\TableRegistry\Search\SearchBySearchableColumnsDefinition;
use Wolfcharaa\TableRegistry\Sort\RegistryDefaultSortDefinition;
use Wolfcharaa\TableRegistry\Sort\RegistrySortDefinition;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;
use Wolfcharaa\TableRegistry\Value\BooleanValue;
use Wolfcharaa\TableRegistry\Value\DateTimeValue;
use Wolfcharaa\TableRegistry\Value\IntegerValue;
use Wolfcharaa\TableRegistry\Value\JsonListValue;
use Wolfcharaa\TableRegistry\Value\PrimitiveValueObjectInterface;
use Wolfcharaa\TableRegistry\Value\RawValue;
use Wolfcharaa\TableRegistry\Value\StringValue;

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
        $providerOutputCache = [];
        $relationCache = [];

        return $this->compileOne($aggregate, $variant, $providerCache, $providerOutputCache, $relationCache);
    }

    /**
     * @param iterable<class-string> $aggregates
     */
    public function compileAll(iterable $aggregates): CompiledTableDefinitionCollection
    {
        $definitions = [];
        $issues = [];
        $providerCache = [];
        $providerOutputCache = [];
        $relationCache = [];

        foreach ($aggregates as $aggregate) {
            try {
                $definition = $this->compileOne(
                    $aggregate,
                    null,
                    $providerCache,
                    $providerOutputCache,
                    $relationCache
                );
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
     * @param array<string, mixed> $providerOutputCache
     * @param array<class-string, RelationMetadata> $relationCache
     */
    private function compileOne(
        string $aggregate,
        ?string $variant,
        array &$providerCache,
        array &$providerOutputCache,
        array &$relationCache
    ): TableRegistryDefinition {
        try {
            return $this->doCompile(
                $aggregate,
                $variant,
                $providerCache,
                $providerOutputCache,
                $relationCache
            );
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
     * @param array<string, mixed> $providerOutputCache
     * @param array<class-string, RelationMetadata> $relationCache
     */
    private function doCompile(
        string $aggregate,
        ?string $variant,
        array &$providerCache,
        array &$providerOutputCache,
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

        $fromAttributes = $this->attributes->instances($reflection, From::class);
        $externalAttributes = $this->attributes->instances($reflection, ExternalRows::class);
        if (\count($fromAttributes) + \count($externalAttributes) !== 1) {
            throw InvalidRegistryDefinitionException::because(
                'Aggregate must declare exactly one From or ExternalRows Attribute.'
            );
        }

        $from = $fromAttributes[0] ?? null;
        $external = $externalAttributes[0] ?? null;
        $joins = $this->joins($reflection);
        $participants = [];
        $isExternal = $external instanceof ExternalRows;

        if ($isExternal) {
            if ($joins !== []) {
                throw InvalidRegistryDefinitionException::because('ExternalRows aggregate cannot declare JOINs.');
            }
            $source = RegistrySourceDefinition::external($external->key);
        } else {
            if (!$from instanceof From) {
                throw new \LogicException('SQL aggregate source was not normalized.');
            }
            $this->assertAlias($from->alias, 'root participant');
            $root = $this->relation($from->model, $providerCache, $providerOutputCache, $relationCache);
            $participants[$from->alias] = $root;

            foreach ($joins as $join) {
                $this->assertAlias($join->alias, 'JOIN participant');
                if (isset($participants[$join->alias])) {
                    throw InvalidRegistryDefinitionException::because(\sprintf(
                        'Duplicate participant alias "%s".',
                        $join->alias
                    ));
                }

                $relation = $this->relation(
                    $join->model,
                    $providerCache,
                    $providerOutputCache,
                    $relationCache
                );
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
        }

        $countSource = $this->optionalAttribute($reflection, CountSource::class);
        if ($countSource instanceof CountSource) {
            if ($isExternal) {
                throw InvalidRegistryDefinitionException::because('ExternalRows aggregate cannot declare CountSource.');
            }
            $source = $source->withCountSource($countSource->source);
        }

        $rowKeys = $this->propertyRowKeys($reflection, $isExternal);
        $columns = $this->columns(
            $reflection,
            $participants,
            $providerCache,
            $providerOutputCache,
            $isExternal,
            $rowKeys
        );
        $definition = new TableRegistryDefinition($table->key, $table->title, $source, $columns);
        foreach ($this->internalInputs(
            $reflection,
            $participants,
            $providerCache,
            $providerOutputCache,
            $isExternal
        ) as $input) {
            $definition = $definition->withRowInput($input);
        }

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

        foreach ($this->attributes->instances($reflection, SqlConstraint::class) as $constraint) {
            if ($constraint instanceof SqlConstraint) {
                if ($isExternal) {
                    throw InvalidRegistryDefinitionException::because(
                        'ExternalRows aggregate cannot declare SQL constraints.'
                    );
                }
                if ($constraint->variant !== null) {
                    throw InvalidRegistryDefinitionException::because(
                        'Variant-scoped SQL constraints require a named aggregate variant.'
                    );
                }
                $definition = $definition->withRuntimeConstraint(
                    new StaticRegistrySqlConstraint($constraint->sql, $constraint->params)
                );
            }
        }

        foreach ($this->attributes->instances($reflection, RuntimeConstraint::class) as $constraint) {
            if ($constraint instanceof RuntimeConstraint) {
                if ($isExternal) {
                    throw InvalidRegistryDefinitionException::because(
                        'ExternalRows aggregate cannot declare runtime SQL constraints.'
                    );
                }
                if ($constraint->variant !== null) {
                    throw InvalidRegistryDefinitionException::because(
                        'Variant-scoped runtime constraints require a named aggregate variant.'
                    );
                }
                $definition = $definition->withRuntimeConstraint($constraint->resolver);
            }
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
     * @return array<string, string>
     */
    private function propertyRowKeys(\ReflectionClass $aggregate, bool $external): array
    {
        $keys = [];
        $occupied = [];

        foreach ($aggregate->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() !== $aggregate->getName()) {
                continue;
            }

            $column = $this->attributes->first($property, Column::class);
            $internal = $this->attributes->has($property, Internal::class);
            if ($column instanceof Column && $internal) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Aggregate property %s::$%s cannot be both Column and Internal.',
                    $aggregate->getName(),
                    $property->getName()
                ));
            }
            if (!$column instanceof Column && !$internal) {
                continue;
            }

            $key = $column instanceof Column ? ($column->key ?? $property->getName()) : $property->getName();
            if ($external && (!$column instanceof Column || $column->mode !== ColumnMode::Output)) {
                $externalField = $this->attributes->first($property, ExternalField::class);
                if ($externalField instanceof ExternalField) {
                    $key = $externalField->key ?? $property->getName();
                }
            }

            $this->assertAlias($key, 'raw row key');
            if (isset($occupied[$key])) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Duplicate aggregate raw row key "%s".',
                    $key
                ));
            }

            $occupied[$key] = true;
            $keys[$property->getName()] = $key;
        }

        return $keys;
    }

    /**
     * @param \ReflectionClass<object> $aggregate
     * @param array<string, RelationMetadata> $participants
     * @param array<class-string, object> $providerCache
     * @param array<string, mixed> $providerOutputCache
     * @return list<RegistryRowInputDefinition>
     */
    private function internalInputs(
        \ReflectionClass $aggregate,
        array $participants,
        array &$providerCache,
        array &$providerOutputCache,
        bool $external
    ): array {
        $inputs = [];

        foreach ($aggregate->getProperties() as $property) {
            if (
                $property->getDeclaringClass()->getName() !== $aggregate->getName()
                || !$this->attributes->has($property, Internal::class)
            ) {
                continue;
            }
            if ($property->isStatic()) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Internal aggregate property %s::$%s must not be static.',
                    $aggregate->getName(),
                    $property->getName()
                ));
            }
            $this->assertInternalCapabilities($property);

            [$select, $_rowType, $_nullable, $rowKey, $hasInput] = $this->columnInput(
                $property,
                $property->getName(),
                $participants,
                $providerCache,
                $providerOutputCache,
                $external
            );
            if (!$hasInput) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Internal aggregate property %s::$%s must declare an input.',
                    $aggregate->getName(),
                    $property->getName()
                ));
            }

            $inputs[] = new RegistryRowInputDefinition($rowKey, $select);
        }

        return $inputs;
    }

    private function assertInternalCapabilities(\ReflectionProperty $property): void
    {
        foreach ([
            PrimaryKey::class,
            Searchable::class,
            Sort::class,
            Label::class,
            LabelFrom::class,
            LabelWith::class,
            HydrateWith::class,
            TextFilter::class,
            IntegerFilter::class,
            BooleanFilter::class,
            NullabilityFilter::class,
            DateRangeFilter::class,
            TimeRangeFilter::class,
            StaticSelectFilter::class,
            PreloadSelectFilter::class,
            AsyncSelectFilter::class,
        ] as $capability) {
            if ($this->attributes->has($property, $capability)) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Internal aggregate property %s::$%s cannot declare %s.',
                    $property->getDeclaringClass()->getName(),
                    $property->getName(),
                    $capability
                ));
            }
        }
    }

    /**
     * @param \ReflectionClass<object> $aggregate
     * @param array<string, RelationMetadata> $participants
     * @param array<class-string, object> $providerCache
     * @param array<string, mixed> $providerOutputCache
     * @param array<string, string> $rowKeys
     * @return list<ColumnRegistryDefinition>
     */
    private function columns(
        \ReflectionClass $aggregate,
        array $participants,
        array &$providerCache,
        array &$providerOutputCache,
        bool $external,
        array $rowKeys
    ): array {
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
                'definition' => $this->column(
                    $property,
                    $column,
                    $key,
                    $participants,
                    $providerCache,
                    $providerOutputCache,
                    $external,
                    $rowKeys
                ),
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
     * @param array<string, mixed> $providerOutputCache
     * @param array<string, string> $rowKeys
     */
    private function column(
        \ReflectionProperty $property,
        Column $metadata,
        string $key,
        array $participants,
        array &$providerCache,
        array &$providerOutputCache,
        bool $external,
        array $rowKeys
    ): ColumnRegistryDefinition {
        $primary = $this->attributes->has($property, PrimaryKey::class);
        $column = $primary
            ? new PrimaryKeyColumnRegistryDefinition($key, $metadata->title)
            : new ColumnRegistryDefinition($key, $metadata->title);

        [$select, $rowType, $nullable, $rawRowKey, $hasInput] = $this->columnInput(
            $property,
            $key,
            $participants,
            $providerCache,
            $providerOutputCache,
            $external
        );
        if (!$hasInput && $metadata->mode !== ColumnMode::Output) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Aggregate property %s::$%s must declare an input.',
                $property->getDeclaringClass()->getName(),
                $property->getName()
            ));
        }
        if ($hasInput && $metadata->mode === ColumnMode::Output) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Output-only aggregate property %s::$%s cannot declare an input.',
                $property->getDeclaringClass()->getName(),
                $property->getName()
            ));
        }
        $column = $column->withRowTypeObject($rowType);
        if ($select !== null) {
            $column = $column->withSelect($select);
        }
        if ($rawRowKey !== $key) {
            $column = $column->withRawRowKey($rawRowKey);
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

        $column = $this->withHydration($column, $property, $rowType, $nullable, $rowKeys);
        $column = $this->withLabel($column, $property, $rowKeys);

        $filter = $this->filter($property, $key, $metadata->title);
        if ($filter !== null) {
            $column = $column->withFilter($filter);
        }

        return $column;
    }

    /**
     * @param array<string, RelationMetadata> $participants
     * @param array<class-string, object> $providerCache
     * @param array<string, mixed> $providerOutputCache
     * @return array{
     *     0:?ColumnSelectDefinition,
     *     1:class-string<PrimitiveValueObjectInterface>,
     *     2:bool,
     *     3:string,
     *     4:bool
     * }
     */
    private function columnInput(
        \ReflectionProperty $property,
        string $key,
        array $participants,
        array &$providerCache,
        array &$providerOutputCache,
        bool $external
    ): array {
        $project = $this->attributes->first($property, Project::class);
        $expression = $this->attributes->first($property, SqlExpression::class);
        $expressionProvider = $this->attributes->first($property, SqlExpressionProvider::class);
        $externalField = $this->attributes->first($property, ExternalField::class);
        $inputs = \array_values(\array_filter([$project, $expression, $expressionProvider, $externalField]));

        if ($inputs === []) {
            $resolved = $this->resolveRawType($property);

            return [null, $resolved['rowType'], $resolved['nullable'], $key, false];
        }
        if (\count($inputs) !== 1) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Aggregate property %s::$%s must declare exactly one input.',
                $property->getDeclaringClass()->getName(),
                $property->getName()
            ));
        }

        if ($external) {
            if (!$externalField instanceof ExternalField) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'External aggregate property %s::$%s must use ExternalField.',
                    $property->getDeclaringClass()->getName(),
                    $property->getName()
                ));
            }
            $resolved = $this->resolveRawType($property);

            return [
                null,
                $resolved['rowType'],
                $resolved['nullable'],
                $externalField->key ?? $property->getName(),
                true,
            ];
        }

        if ($externalField instanceof ExternalField) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'SQL aggregate property %s::$%s cannot use ExternalField.',
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
                $field->nullable(),
                $key,
                true,
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
            $outputKey = 'expression:' . $expressionProvider->provider;
            if (!\array_key_exists($outputKey, $providerOutputCache)) {
                $providerOutputCache[$outputKey] = $provider();
            }
            $sql = $providerOutputCache[$outputKey];
            if (!\is_string($sql)) {
                throw new \LogicException('SQL expression provider returned a non-string value.');
            }
        }
        if ($sql === null || \trim($sql) === '') {
            throw InvalidRegistryDefinitionException::because('SQL expression must not be empty.');
        }

        $resolved = $this->resolveRawType($property);

        return [
            ColumnSelectDefinition::sqlExpression($key, $sql),
            $resolved['rowType'],
            $resolved['nullable'],
            $key,
            true,
        ];
    }

    /**
     * @return array{rowType:class-string<PrimitiveValueObjectInterface>,nullable:bool}
     */
    private function resolveRawType(\ReflectionProperty $property): array
    {
        $rawType = $this->attributes->first($property, RawType::class);
        if ($this->attributes->has($property, HydrateWith::class) && !$rawType instanceof RawType) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Hydrated computed or external property %s::$%s must declare RawType.',
                $property->getDeclaringClass()->getName(),
                $property->getName()
            ));
        }

        $field = $rawType instanceof RawType
            ? new Field(rowType: $rawType->rowType, nullable: $rawType->nullable)
            : new Field();

        return $this->types->resolve($property, $field);
    }

    /**
     * @param class-string<PrimitiveValueObjectInterface> $rowType
     * @param array<string, string> $rowKeys
     */
    private function withHydration(
        ColumnRegistryDefinition $column,
        \ReflectionProperty $property,
        string $rowType,
        bool $nullable,
        array $rowKeys
    ): ColumnRegistryDefinition {
        $hydration = $this->attributes->first($property, HydrateWith::class);
        if (!$hydration instanceof HydrateWith) {
            $this->assertRawTypeAssignable($property, $rowType, $nullable);

            return $column;
        }

        if (!\class_exists($hydration->resolver) || !\is_subclass_of(
            $hydration->resolver,
            ColumnValueHydratorInterface::class
        )) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Hydrator "%s" must implement %s.',
                $hydration->resolver,
                ColumnValueHydratorInterface::class
            ));
        }

        $this->assertHydratorReturnType($property, $hydration);

        return $column->withHydration(
            $hydration->resolver,
            $this->resolverRowKeys($property, $hydration->inputs, $rowKeys)
        );
    }

    /**
     * @param array<string, string> $rowKeys
     */
    private function withLabel(
        ColumnRegistryDefinition $column,
        \ReflectionProperty $property,
        array $rowKeys
    ): ColumnRegistryDefinition {
        $label = $this->attributes->first($property, Label::class);
        $labelFrom = $this->attributes->first($property, LabelFrom::class);
        $labelWith = $this->attributes->first($property, LabelWith::class);
        $labels = \array_values(\array_filter([$label, $labelFrom, $labelWith]));
        if (\count($labels) > 1) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Aggregate property %s::$%s declares more than one label strategy.',
                $property->getDeclaringClass()->getName(),
                $property->getName()
            ));
        }

        if ($label instanceof Label) {
            return $column->withLabel($label->text);
        }
        if ($labelFrom instanceof LabelFrom) {
            $keys = $this->resolverRowKeys($property, [$labelFrom->property], $rowKeys);

            return $column->withLabel(
                new LabelFromRowValue($keys[0], $labelFrom->fallbackToValue),
                $keys
            );
        }
        if ($labelWith instanceof LabelWith) {
            if (!\class_exists($labelWith->resolver) || !\is_subclass_of(
                $labelWith->resolver,
                ColumnValueLabelResolverInterface::class
            )) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Label resolver "%s" must implement %s.',
                    $labelWith->resolver,
                    ColumnValueLabelResolverInterface::class
                ));
            }

            return $column->withLabel(
                $labelWith->resolver,
                $this->resolverRowKeys($property, $labelWith->inputs, $rowKeys)
            );
        }

        return $column;
    }

    /**
     * @param list<string> $properties
     * @param array<string, string> $rowKeys
     * @return list<string>
     */
    private function resolverRowKeys(
        \ReflectionProperty $consumer,
        array $properties,
        array $rowKeys
    ): array {
        $resolved = [];
        foreach ($properties as $property) {
            $rowKey = $rowKeys[$property] ?? null;
            if ($rowKey === null) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Resolver on %s::$%s references unknown aggregate property "%s".',
                    $consumer->getDeclaringClass()->getName(),
                    $consumer->getName(),
                    $property
                ));
            }
            if (!\in_array($rowKey, $resolved, true)) {
                $resolved[] = $rowKey;
            }
        }

        return $resolved;
    }

    /**
     * @param class-string<PrimitiveValueObjectInterface> $rowType
     */
    private function assertRawTypeAssignable(
        \ReflectionProperty $property,
        string $rowType,
        bool $nullable
    ): void {
        $source = match ($rowType) {
            StringValue::class, DateTimeValue::class => 'string',
            IntegerValue::class => 'int',
            BooleanValue::class => 'bool',
            JsonListValue::class => 'array',
            RawValue::class => 'mixed',
            default => $this->customPrimitiveValueType($rowType),
        };

        if (!$this->targetAccepts($property->getType(), $source, $nullable)) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Raw value type %s%s is not assignable to aggregate property %s::$%s.',
                $nullable ? '?' : '',
                $source,
                $property->getDeclaringClass()->getName(),
                $property->getName()
            ));
        }
    }

    /**
     * @param class-string<PrimitiveValueObjectInterface> $rowType
     */
    private function customPrimitiveValueType(string $rowType): string
    {
        $returnType = (new \ReflectionMethod($rowType, 'getValue'))->getReturnType();
        if (!$returnType instanceof \ReflectionNamedType || $returnType->getName() === 'mixed') {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Primitive row type "%s" must declare a concrete getValue() return type.',
                $rowType
            ));
        }

        return $returnType->getName();
    }

    private function assertHydratorReturnType(
        \ReflectionProperty $property,
        HydrateWith $hydration
    ): void {
        $returnType = (new \ReflectionMethod($hydration->resolver, '__invoke'))->getReturnType();
        if (
            $returnType === null
            || ($returnType instanceof \ReflectionNamedType && $returnType->getName() === 'mixed')
        ) {
            if (!$hydration->unchecked) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Hydrator "%s" must declare a concrete return type or set unchecked.',
                    $hydration->resolver
                ));
            }

            return;
        }

        foreach ($this->reflectionSourceTypes($returnType) as [$source, $nullable]) {
            if (!$this->targetAccepts($property->getType(), $source, $nullable)) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Hydrator "%s" return type is not assignable to %s::$%s.',
                    $hydration->resolver,
                    $property->getDeclaringClass()->getName(),
                    $property->getName()
                ));
            }
        }
    }

    /**
     * @return non-empty-list<array{0:string,1:bool}>
     */
    private function reflectionSourceTypes(\ReflectionType $type): array
    {
        if ($type instanceof \ReflectionNamedType) {
            return [[$type->getName(), $type->allowsNull()]];
        }
        if ($type instanceof \ReflectionUnionType) {
            $types = [];
            foreach ($type->getTypes() as $member) {
                if (!$member instanceof \ReflectionNamedType) {
                    throw InvalidRegistryDefinitionException::because(
                        'Hydrator intersection return types are not supported for aggregate values.'
                    );
                }
                if ($member->getName() === 'null') {
                    continue;
                }
                $types[] = [$member->getName(), $type->allowsNull()];
            }

            return $types !== [] ? $types : [['null', true]];
        }

        throw InvalidRegistryDefinitionException::because(
            'Hydrator intersection return types are not supported for aggregate values.'
        );
    }

    private function targetAccepts(?\ReflectionType $target, string $source, bool $nullable): bool
    {
        if ($target === null) {
            return false;
        }
        if ($nullable && !$target->allowsNull()) {
            return false;
        }
        if ($target instanceof \ReflectionUnionType) {
            foreach ($target->getTypes() as $member) {
                if (
                    (!$member instanceof \ReflectionNamedType || $member->getName() !== 'null')
                    && $this->targetAccepts($member, $source, false)
                ) {
                    return true;
                }
            }

            return false;
        }
        if ($target instanceof \ReflectionIntersectionType) {
            foreach ($target->getTypes() as $member) {
                if (!$this->targetAccepts($member, $source, false)) {
                    return false;
                }
            }

            return true;
        }
        if (!$target instanceof \ReflectionNamedType) {
            return false;
        }

        $targetName = $target->getName();
        if ($targetName === 'mixed') {
            return true;
        }
        if ($source === 'mixed' || \in_array($source, ['void', 'never', 'null'], true)) {
            return false;
        }
        if ($target->isBuiltin() || \in_array($source, ['string', 'int', 'bool', 'float', 'array'], true)) {
            return $targetName === $source;
        }

        return \is_a($source, $targetName, true);
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
     * @param array<string, mixed> $providerOutputCache
     * @param array<class-string, RelationMetadata> $relationCache
     */
    private function relation(
        string $model,
        array &$providerCache,
        array &$providerOutputCache,
        array &$relationCache
    ): RelationMetadata {
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
            $outputKey = 'source:' . $providerClass;
            if (!\array_key_exists($outputKey, $providerOutputCache)) {
                $providerOutputCache[$outputKey] = $provider();
            }
            $source = $providerOutputCache[$outputKey];
            if (!$source instanceof \Wolfcharaa\TableRegistry\Metadata\RelationSourceMetadata) {
                throw new \LogicException('Relation source provider returned invalid metadata.');
            }
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
