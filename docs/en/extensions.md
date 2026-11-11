# Extending TableRegistry

[Русский](../extensions.md)

This guide documents the supported application extension points in `v0.7`: their purpose, consumer-owned code,
composition, invocation lifecycle, and failure behavior. Prefer built-in Attributes and definitions for ordinary
integrations; implement an interface only when the project needs to change behavior.

Exact signatures and the compatibility promise live in [`public-api.md`](public-api.md).

## Extension map

| Task | Contract | Composition | Invocation |
| --- | --- | --- | --- |
| Configuration-backed relation source | `RelationSourceProviderInterface` | `RelationSourceProvider` plus compile-time resolver | Attribute compilation |
| Configuration-backed SQL expression | `SqlExpressionProviderInterface` | `SqlExpressionProvider` plus compile-time resolver | Attribute compilation |
| Alternate relation metadata source | `RelationMetadataProviderInterface` | `AttributeTableRegistryCompiler` constructor | Attribute compilation |
| Framework/DI compile-time resolution | `CompileTimeProviderResolverInterface` | `AttributeTableRegistryCompiler` constructor | Attribute compilation |
| Value hydration or labels | `ColumnValueHydratorInterface`, `ColumnValueLabelResolverInterface` | `HydrateWith`/`LabelWith` or object API plus registry invoker | Row projection |
| Actor/tenant table predicate | `RegistrySqlRuntimeConstraintInterface` | `RuntimeConstraint` or `withRuntimeConstraint()` | Rows/count/export compilation |
| Actor/tenant standalone-select predicate | `SelectRuntimeConstraintInterface` | `SelectDefinition::withRuntimeConstraint()` | Select compilation |
| Framework/DI runtime resolution | `RegistryResolverInvokerInterface`, `ResolverInvokerInterface` | SQL compiler/projector constructors | SQL compilation/projection |
| Project-specific filter metadata | `RegistryFilterDefinitionInterface`, `RegistryFilterExpressionInterface` | Object-authored column `withFilter()` | Validation and registry SQL compilation |
| Project-specific search metadata | `RegistrySearchDefinitionInterface` | `TableRegistryDefinition::withSearch()` | Serialization, validation, and registry SQL compilation |
| Another standalone-select SQL backend | `SelectCompilerInterface` | Application DI/composition | Explicit application call |

Place implementations beside the owning application read model or in its infrastructure adapter. The package does
not scan the filesystem or auto-register implementations.

## Compile-time providers

`RelationSourceProviderInterface` returns resolved `RelationSourceMetadata`; `SqlExpressionProviderInterface` returns
a trusted SQL string. They may read stable configuration such as a schema or view name. They must not depend on a
request, actor, or per-request tenant.

Zero-argument classes work with `NativeCompileTimeProviderResolver`. For constructor dependencies, inject
`PsrContainerCompileTimeProviderResolver`:

```php
$providerResolver = new PsrContainerCompileTimeProviderResolver($container);
$compiler = new AttributeTableRegistryCompiler(providers: $providerResolver);
$definition = $compiler->compile(UsersTable::class);
```

The container lookup key is the provider class-string. Missing entries use the native fallback. The entry must be an
object. A source provider must implement `RelationSourceProviderInterface` and return
`RelationSourceMetadata::relation()` or `::raw()`; an expression provider must implement
`SqlExpressionProviderInterface` and return a non-empty SQL expression.

Within one `compile()`, `compileAll()`, or `compileVariants()` operation, each provider object and output is evaluated
once. The result is frozen into the immutable definition; there is no global cache across operations. Compile the
catalog once during application composition/bootstrap, keep definitions in the application's shared registry, and
reuse them for requests.

Provider exceptions become `AttributeCompilationException`: `source.provider` for sources and
`projection.provider` for expressions. The issue identifies the consumer, Attribute, and provider. Repeated uses in
the same operation receive the memoized output or failure.

Runnable PSR-container example: [`../examples/12-attribute-providers.php`](../examples/12-attribute-providers.php).

## Custom relation metadata provider

Use `RelationMetadataProviderInterface` when relation facts already exist in project metadata and should not be
duplicated as Attributes. `metadata(class-string $model)` returns `RelationMetadata` or `null` for an unsupported
model. The aggregate still references the model class through `From`/`Join`.

```php
final class ProjectRelationMetadataProvider implements RelationMetadataProviderInterface
{
    public function metadata(string $model): ?RelationMetadata
    {
        if ($model !== LegacyUser::class) {
            return null;
        }

        return new RelationMetadata(
            RelationSourceMetadata::relation('public.legacy_user'),
            [
                new RelationFieldMetadata('id', 'user_id', IntegerValue::class, false),
                new RelationFieldMetadata('email', 'email_address', StringValue::class, false),
            ]
        );
    }
}

$compiler = new AttributeTableRegistryCompiler(
    relations: new ProjectRelationMetadataProvider()
);
```

Metadata needs at least one field, unique logical names, valid physical identifiers, and row types implementing
`PrimitiveValueObjectInterface`. Keep it request-independent. A custom provider fully replaces
`AttributeRelationMetadataProvider`; a composite/fallback combining both sources belongs to the application.

## Runtime constraints

A runtime constraint receives a typed context and returns a condition or `null`. Inject dependencies through its
constructor and bind dynamic values only through `context->param()`.

```php
final class AllowedDepartmentConstraint implements RegistrySqlRuntimeConstraintInterface
{
    public function __construct(private AccessPolicy $policy)
    {
    }

    public function __invoke(RegistrySqlRuntimeContext $context): ?RegistrySqlCondition
    {
        $ids = $this->policy->departmentIds();
        if ($ids === []) {
            return RegistrySqlCondition::impossible();
        }

        $params = array_map(
            static fn (int $id): string => ':' . $context->param($id),
            $ids
        );

        return RegistrySqlCondition::raw('department_id IN (' . implode(', ', $params) . ')');
    }
}
```

Attach the class-string through `RuntimeConstraint` or `withRuntimeConstraint()`. Supply one
`PsrContainerRegistryResolverInvoker` to `PostgresRegistrySqlCompiler` and `RegistryRowsProjector` when the same
container resolves constraints, hydrators, and labels. A table constraint applies to rows, count, and export.

Standalone selects use `SelectRuntimeConstraintInterface`, `SelectRuntimeContext`, and
`Select\Runtime\PsrContainerResolverInvoker`. Generic-select `param()` returns the complete placeholder with `:`;
the legacy registry context returns its name without `:`. A wrong return type produces a select or registry
compilation error. Route authorization remains separate.

Runnable paths: [`../examples/04-runtime-constraint.php`](../examples/04-runtime-constraint.php) and
[`../examples/07-standalone-option-list.php`](../examples/07-standalone-option-list.php).

## Hydrators and labels

`ColumnValueHydratorInterface` converts a raw `PrimitiveValueObjectInterface` into a domain/view value.
`ColumnValueLabelResolverInterface` returns its string label. Both receive scoped `RegistryRowData` and the current
`ColumnRegistryDefinition`; Attribute-authored definitions expose only inputs listed by `HydrateWith` or `LabelWith`.

Place the resolver beside its read model, register its class-string in the container, and use
`PsrContainerRegistryResolverInvoker` when building the projector. The native invoker supports callables and
zero-argument invokable classes only. Container entries must be callable. Interface or return-type mismatches fail
during Attribute compilation or projection.

```php
$invoker = new PsrContainerRegistryResolverInvoker($container);
$projector = new RegistryRowsProjector($invoker);
$rows = $projector->project($definition, $databaseRows);
```

Complete container/label example:
[`../examples/06-psr-container-invoker.php`](../examples/06-psr-container-invoker.php).

## Custom filters and search metadata

Custom filters are available through object-authored `ColumnRegistryDefinition::withFilter()`. `normalize()` validates
and transforms the transport value; `expression()` returns its normalized operator/value. The registry SQL compiler
has fixed expression semantics: arrays become `IN`, `contains` becomes parameterized `ILIKE`, `is_null` and
`is_not_null` become null predicates, and every other operator becomes equality. The expression cannot select an
arbitrary SQL column; compilation targets the owning column.

```php
final class CsvIntegerFilter extends BaseRegistryFilterDefinition
{
    public function __construct(string $queryKey, string $columnKey, string $title)
    {
        parent::__construct($queryKey, $title, $columnKey, 'integer[]', ['in']);
    }

    public function normalize(mixed $value): mixed
    {
        if (!is_string($value) || preg_match('/^\d+(,\d+)*$/', $value) !== 1) {
            return null;
        }

        return array_map('intval', explode(',', $value));
    }
}

$column = (new ColumnRegistryDefinition('departmentId', 'Department'))
    ->withFilter(new CsvIntegerFilter('department_ids', 'departmentId', 'Departments'));
```

The validator rejects `null` and an empty array. Serialized metadata must expose the same query key/format understood
by transport code. The `v0.7` Attribute frontend accepts built-in filter Attributes only; use the object API for a
custom filter until it has a first-class Attribute.

`RegistrySearchDefinitionInterface` controls frontend metadata, query key, and enabled state only. SQL search still
uses columns marked searchable; this interface is not an arbitrary search algorithm. For different matching, use a
fixed SQL-expression searchable column or a standalone select, never request-provided SQL.

## Custom resolver invoker and select compiler

`RegistryResolverInvokerInterface` and `Select\Runtime\ResolverInvokerInterface` are framework adapters. An
implementation receives a callable/class-string and ordered argument map, invokes it with the values in that order,
and returns the result unchanged. The built-in PSR-container adapters cover most applications. Throw resolution/call
errors; do not silently return `null`.

`SelectCompilerInterface` is the port for another standalone-select SQL backend. Its `compile()` returns a rows
`CompiledSelect`; `compileReadPlan()` returns rows plus optional count. The implementation must validate
`SelectQueryInput`, bind all dynamic values, and preserve criteria, runtime-constraint, sorting, and pagination
semantics. Register it in application DI and invoke it explicitly; TableRegistry does not select a dialect from a
connection string.

```php
final class ProjectSelectReader
{
    public function __construct(private SelectCompilerInterface $compiler)
    {
    }

    public function plan(SelectDefinition $definition, SelectQueryInput $query): CompiledSelectReadPlan
    {
        return $this->compiler->compileReadPlan($definition, $query);
    }
}
```

`PostgresSelectCompiler` is the reference implementation. Before wiring another backend, test SQL/params,
rows/count parity, invalid input, and every runtime constraint.

## Verifying an extension

A consumer contract test should cover:

1. composition and the concrete interface implementation;
2. successful output;
3. invalid input or a wrong return type;
4. the empty/no-access runtime-constraint path;
5. identical scope across rows, count, and export;
6. no raw request values in SQL;
7. compiled-definition reuse without repeated compile-time I/O.

Do not depend on `@internal` symbols. Before upgrading, compare interface signatures with
[`public-api.md`](public-api.md) and run the consumer integration suite against the actually resolved Composer
version.
