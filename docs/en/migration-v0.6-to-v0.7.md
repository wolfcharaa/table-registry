# Migration `v0.6.x` → `v0.7.0`

[Русский](../migration-v0.6-to-v0.7.md)

`v0.7.0` adds an Attribute authoring frontend while retaining object-authored definitions. Updating the dependency
does not require rewriting existing tables: both paths emit one `TableRegistryDefinition` and can coexist.

## Update the package

Use an exact release candidate before the final tag, then move to the minor line after a project pilot:

```bash
composer require romanfedorskij/table-registry:"0.7.0-rc.1" --with-all-dependencies
composer show romanfedorskij/table-registry
composer check-platform-reqs
```

After the final release:

```bash
composer require romanfedorskij/table-registry:"^0.7.0" --with-all-dependencies
```

The minimum PHP version remains `8.2`. Do not use `--ignore-platform-reqs`. If the project synchronizes bundled
skills, update them after Composer update.

## What was added

- Attributes for relation models, aggregate projection, joins, filters, resolvers, constraints, and finite variants;
- `AttributeTableRegistryCompiler` with explicit `compile`/`compileAll`/`compileVariants` registration;
- compile-time provider ports with native and PSR-container resolution;
- typed external-row sources and internal resolver-only row inputs;
- ordered `AttributeCompilationIssue` diagnostics with stable codes.

Object constructors/fluent methods, legacy JSON/SQL/projection behavior, and the standalone select API are not removed
or deprecated.

## Pilot one table

1. Keep the current definition factory as the parity oracle.
2. Extract a reusable relation class with `Relation`/`Field` metadata.
3. Add an aggregate with `Table`, `From`/`Join`, and explicit `Column` projection.
4. Compile the new definition alongside the old one.
5. Compare serialized definition, rows/count/export SQL and params, projection, and export columns.
6. Switch registration only after parity; remove the old factory in a separate reversible commit.

The minimal Attribute path is in [`../examples/08-attribute-table.php`](../examples/08-attribute-table.php), with a
JOIN in [`../examples/09-attribute-join.php`](../examples/09-attribute-join.php).

## Object API mapping

| Before `v0.7` | Attribute path |
| --- | --- |
| `RegistrySourceDefinition::from()` | relation `Relation` + aggregate `From` |
| `withInnerJoin()` / `withLeftJoin()` | `Join` + `FieldMatch` or `RawJoinCondition` |
| `new ColumnRegistryDefinition()` | aggregate property `Column` + input Attribute |
| `ColumnSelectDefinition::sqlExpression()` | `SqlExpression` / `SqlExpressionProvider` |
| auxiliary select for a resolver | `Internal` property |
| fixed constraint callback | `SqlConstraint` |
| DI class-string constraint | `RuntimeConstraint` |
| separate active/archive factories | repeatable `Variant` family |

Do not move request-dependent actor/tenant restrictions into compile-time Attributes. Add them after compilation with
the existing immutable `withRuntimeConstraint()`.

## Cases that may stay on the object API

- definition shape genuinely depends on runtime request/application state;
- the consumer builds columns/options from a dynamic catalog;
- migration does not achieve JSON/SQL/projection parity;
- an existing builder is an intentional domain convention, not repeated static metadata.

Attribute and object definitions can be registered side by side through `compileAll()` and ordinary application
factories.

## Rollback

Attribute declarations do not change the database schema. For rollback, restore application registration to the old
factory and return the dependency constraint to the last verified `v0.6.x`:

```bash
composer require romanfedorskij/table-registry:"^0.6.0" --with-all-dependencies
```

Rollback consumer code importing the new `Attribute\*`, `Compiler\*`, external-row, or row-input APIs at the same
time. Do not leave those imports after a downgrade.
