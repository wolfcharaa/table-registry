# Attribute-authored tables

[Русский](../attributes.md)

Starting with the `v0.7` line, PHP Attributes are the recommended authoring path for static table read models. The
compiler reflects ordinary classes without instantiating them and emits the same `TableRegistryDefinition` used by
the object-authored API. SQL compilation, projection, validation, and XLSX export have no separate Attribute path.

Complete minimal source: [`../examples/08-attribute-table.php`](../examples/08-attribute-table.php).
The fragments below show metadata shape and assume imports/autoload from that complete example.

## Two class roles

A relation model owns data-source facts only:

```php
#[Relation('public.users')]
final class UserReadModel
{
    #[Field('user_id')]
    protected int $id;

    #[Field('email_address')]
    protected string $email;
}
```

A separate aggregate owns the public table and explicitly selects its projection:

```php
#[Table('users', 'Users')]
#[From(UserReadModel::class, 'user')]
final class UsersTable
{
    #[Column('ID', 10)]
    #[Project('user')]
    #[PrimaryKey]
    protected int $id;

    #[Column('Email', 20)]
    #[Project('user')]
    protected string $email;
}

$definition = (new AttributeTableRegistryCompiler())->compile(UsersTable::class);
```

A relation model can be reused under different aliases and by different aggregates. Adding a `Field` never expands a
frontend contract by itself: only properties carrying `Column` in that aggregate are exposed.

## Joining models

The aggregate owns topology. Ordinary `INNER`/`LEFT JOIN` declarations use `Join` with one or more `FieldMatch`
pairs; advanced PostgreSQL conditions use the explicit trusted `RawJoinCondition` escape hatch. Validation only checks
declared aliases and fields in memory. It does not inspect a database schema or open a connection.

Executable example: [`../examples/09-attribute-join.php`](../examples/09-attribute-join.php).

## Computed, internal, and external values

- `SqlExpression` stores a simple trusted SQL expression; `SqlExpressionProvider` supplies a DI/configuration-aware
  compile-time expression.
- `Internal` selects resolver-only data that is absent from JSON, projected values, query capabilities, and export.
- `LabelFrom`, `LabelWith`, and `HydrateWith` see only explicitly declared row inputs.
- A hydrated computed/external value requires `RawType`. A concrete hydrator return type must be assignable to the
  aggregate property; `unchecked: true` permits only missing or `mixed` return types.
- `ColumnMode::Output` reads an application-prepared value and adds no SQL select.

## Variants

Repeatable `Variant` declares a finite family such as active/archive. Exactly one variant has `isDefault: true`.
Common properties participate everywhere; `OnlyInVariant` and `ExcludeFromVariant` change membership. Variant-scoped
`Pagination`, `DefaultSort`, `CountSource`, `SqlConstraint`, and `RuntimeConstraint` replace the common setting of the
same kind.

```php
$active = $compiler->compile(OrdersTable::class);
$archive = $compiler->compile(OrdersTable::class, 'archive');
$family = $compiler->compileVariants(OrdersTable::class);
```

Every operation validates the complete family. `compileAll()` returns only each aggregate's default definition and
rejects duplicate registry keys. Executable example:
[`../examples/10-attribute-variants.php`](../examples/10-attribute-variants.php).

## External rows

`ExternalRows` represents rows supplied by the application outside the PostgreSQL compiler. `ExternalField` maps a
public/internal property to a provider row key. Projection remains shared, while SQL compilation throws
`UnsupportedRegistrySourceException`.

Executable example: [`../examples/11-attribute-external-rows.php`](../examples/11-attribute-external-rows.php).

## Host boundary

The Attribute compiler receives explicit class strings and stable compile-time providers only. It performs no
filesystem discovery, SQL execution, network I/O, request parsing, or authorization. Add request-dependent tenant or
actor scope after compilation through immutable `withRuntimeConstraint()`; the application still selects the DB
adapter, executes SQL with params, and owns the response lifecycle.

See [`attribute-reference.md`](attribute-reference.md) for the complete Attribute matrix, provider lifecycle, and
error codes. The object API remains supported for dynamic definitions and incremental migration.
