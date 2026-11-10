# Attribute reference

[Русский](../attribute-reference.md)

This reference targets the `v0.7` line. Start with [`attributes.md`](attributes.md) for the first workflow and use
[`../public-api.md`](../public-api.md) for exact public signatures.

## Roles and sources

| Role | Required Attributes | Purpose |
| --- | --- | --- |
| Relation model | exactly one `Relation`, `RawRelation`, or `RelationSourceProvider` | Reusable physical/derived source and `Field` metadata |
| Aggregate | `Table` or a `Variant` family; exactly one `From` or `ExternalRows` | Final table/read-model contract |
| Mapped source | `MappedFields` on a parent/trait | Explicit reusable field import without a source |

Roles are mutually exclusive. Aggregates are terminal and cannot participate in another aggregate. The compiler reads
explicit class strings only and never instantiates annotated classes.

## Class-level Attributes

| Attribute | Repeatable | Semantics |
| --- | ---: | --- |
| `Table(key, title)` | no | Single aggregate identity; incompatible with `Variant` |
| `Variant(name, key, title, isDefault)` | yes | Named finite family; exactly one default |
| `From(model, alias)` | no | Root relation participant |
| `ExternalRows(key)` | no | Non-SQL provider rows; forbids `From`, `Join`, count, and SQL constraints |
| `Join(model, alias, type, position, on)` | yes | Ordered `Inner`/`Left` join; `FieldMatch[]` or `RawJoinCondition` |
| `CountSource(source, variant)` | yes | Optional count source; a variant value replaces common |
| `Search` | no | Search query key and enabled flag |
| `Pagination(..., variant)` | yes | Common/variant pagination policy |
| `DefaultSort(column, direction, variant)` | yes | Common/variant default sort; column must exist |
| `SqlConstraint(sql, params, variant)` | yes | Trusted fixed predicate with literal params |
| `RuntimeConstraint(resolver, variant)` | yes | Class-string `RegistrySqlRuntimeConstraintInterface` |
| `Relation(identifier, connection)` | no | Qualified relation/view identifier |
| `RawRelation(sql, connection)` | no | Trusted reusable derived source |
| `RelationSourceProvider(provider)` | no | Compile-time `RelationSourceProviderInterface` |
| `MappedFields` | no | Enables explicit field import from a parent/trait |
| `OverrideField` / `ExcludeField` | yes | Changes or removes an imported field only |

`Join` positions and aliases are unique. Every SQL participant must use one connection. Structured joins validate
metadata references only; PostgreSQL remains responsible for physical relations and columns.

## Property-level Attributes

| Group | Attributes | Rule |
| --- | --- | --- |
| Relation mapping | `Field` | Physical column, row value-object type, and nullability |
| Public column | `Column`, `PrimaryKey` | Unique key/positive position; exactly one visible primary key |
| SQL input | `Project`, `SqlExpression`, `SqlExpressionProvider` | Exactly one input for an ordinary SQL column |
| External input | `ExternalField` | Provider row key for an external aggregate |
| Resolver-only input | `Internal` | No public/payload/query/export capability |
| Raw value | `RawType` | Required for hydrated computed/external inputs |
| Presentation | `Label`, `LabelFrom`, `LabelWith`, `HydrateWith` | One label strategy; complex resolvers list explicit inputs |
| Query | `Searchable`, `Sort`, filter Attributes | Query capabilities belong to the aggregate column |
| Membership | `OnlyInVariant`, `ExcludeFromVariant` | Mutually exclusive references to existing variants only |

Filter Attributes are `TextFilter`, `IntegerFilter`, `BooleanFilter`, `NullabilityFilter`, `DateRangeFilter`,
`TimeRangeFilter`, `StaticSelectFilter`, `PreloadSelectFilter`, and `AsyncSelectFilter`. A property accepts one filter.
`ColumnMode::Visible` is ordinary, `Payload` is hidden from definition JSON, and `Output` adds no SQL select.

PHP visibility, constructors, methods, initialized/promoted/readonly properties do not affect compilation. Annotated
static properties are rejected.

## Important defaults

- `Column::key`, `Project::field`, `Field::column`, and `ExternalField::key` default to the property name.
- `Field::rowType` and nullability are inferred from the PHP property type when omitted.
- A filter query key defaults to its column key; range keys default to `<key>_from` and `<key>_to`.
- `Search` uses `search`; `Pagination` uses `page`/`per_page`, default `25`, maximum `250`.
- `Sort` without an expression uses the column select expression.
- `Column::exportable = null` preserves mode policy; payload/output modes are not exportable by default.
- `LabelFrom` falls back to the original value when its input is `null`; set `fallbackToValue: false` to disable it.

Exact constructor signatures and enum cases are frozen in [`../public-api.md`](../public-api.md) and its signature
snapshot.

## Providers and resolvers

`AttributeTableRegistryCompiler` uses `NativeCompileTimeProviderResolver` by default; it constructs zero-argument
providers only. For DI, pass `PsrContainerCompileTimeProviderResolver`; it reads a PSR container and falls back to the
native resolver.

A provider object and output are evaluated once per `compile()`, `compileAll()`, or `compileVariants()` operation and
then frozen into the definition. Providers receive no request/query/user context. A failure while computing provider
output is memoized for that operation and returned as a location-bound `AttributeCompilationIssue`.

`LabelWith` and `HydrateWith` receive scoped `RegistryRowData` containing explicitly listed aggregate properties only.
`LabelFrom` is the one-value shortcut. DI-managed runtime class strings are resolved during SQL/projection through
`RegistryResolverInvokerInterface`, not by the compile-time provider resolver.

Compile once during host composition/bootstrap and reuse immutable definitions. The compiler has no global cache;
provider “once” means once per compilation operation. See [`extensions.md`](extensions.md) for full composition and
failure contracts.

## Trusted SQL boundary

Developer-controlled SQL is accepted only by explicitly SQL-oriented metadata: `RawRelation`, `RawJoinCondition`,
`SqlExpression`, provider outputs, `Sort` expressions, `SqlConstraint`, and the object API. Never interpolate request
values there. `SqlConstraint::params` are rebound by the runtime parameter binder; dynamic values use filters/query
input or runtime constraints.

The compiler performs no database/schema/network validation. It checks roles, Attribute structure, aliases, metadata
fields, types, positions, capabilities, connections, and every variant, then delegates the finished definition to the
existing runtime validators.

## Compilation API

```php
$definition = $compiler->compile(Aggregate::class);             // sole/default
$archive = $compiler->compile(Aggregate::class, 'archive');     // explicit variant
$catalog = $compiler->compileAll([A::class, B::class]);         // defaults by registry key
$family = $compiler->compileVariants(Aggregate::class);         // all variants by name
```

An operation returns one complete immutable result or one `AttributeCompilationException` with ordered issues. It
never returns a partial collection.

## Stable issue codes

The list is frozen by `tests/Contract/fixtures/attribute-compilation-issue-codes.json`:

`alias.invalid`, `attribute.invalid`, `capability.invalid`, `catalog.duplicate_key`, `definition.invalid`,
`field.invalid`, `inheritance.invalid`, `join.invalid`, `position.invalid`, `projection.invalid`,
`projection.provider`, `resolver.invalid`, `role.conflict`, `source.invalid`, `source.provider`, `type.invalid`, and
`variant.invalid`.

Every issue contains a code, message, and consumer class. Variant/property/Attribute/provider fields are populated
when the failure has that exact location.
