# Standalone select and option lists

[Русский](../select-and-options.md)

`SelectDefinition` describes a read model without `TableRegistryDefinition`, a primary key, or frontend table
metadata. Use it for preload/async options, dependent lists, selected-value restoration, and small project-specific
reads. Transport parsing, SQL execution, authorization, routes, and HTTP envelopes remain application-owned.

The fragments below are partial and assume the imports/bootstrap shown in the complete executable example.

## 1. Describe the select

```php
$select = SelectDefinition::from(
    SelectSourceDefinition::relation('public.organization', 'o'),
    [
        SelectFieldDefinition::column('o.id', 'value'),
        SelectFieldDefinition::column('o.name', 'label'),
        SelectFieldDefinition::column('o.code', 'code'),
    ]
)
    ->withCriterion(SelectCriterionDefinition::equal(
        'departmentId',
        'o.department_id',
        SelectValueType::Integer
    ))
    ->withSearch(SelectSearchDefinition::contains(['o.name', 'o.code']))
    ->withOrderBy(SelectOrderDefinition::ascending('o.name'))
    ->withOrderBy(SelectOrderDefinition::ascending('o.id'))
    ->withPagination(SelectPaginationDefinition::limit(defaultLimit: 25, maxLimit: 100));

$options = OptionListDefinition::from(
    select: $select,
    valueAlias: 'value',
    labelAlias: 'label',
    valueType: SelectValueType::Integer
)->withCode('code');
```

Declare every user-controlled capability in advance: criterion operator/type, search expressions, allowed sorts, and
pagination. A paginated definition needs deterministic order before `withPagination()`.

`OptionListDefinition` maps select aliases to stable `value`/`label` plus optional `code`, `description`, and `meta`.
Required aliases are explicit and values are converted to the declared string/integer type.

## 2. Build canonical input

The application converts HTTP/CLI aliases and scalar strings into strict `SelectQueryInput`. The library neither reads
requests nor coerces strings to integers implicitly.

```php
$query = SelectQueryInput::from(
    search: 'central',
    criteria: ['departmentId' => 10],
    limit: 25
);

(new SelectQueryValidator())->validate($select, $query)->assertValid();
```

Query input contains no SQL, operators, callbacks, request objects, actors, or services. For selected-value hydration,
declare a separate `oneOf` criterion instead of bypassing the definition.

## 3. Compile and execute SQL

```php
$compiled = (new PostgresSelectCompiler())->compile($select, $query);
$databaseRows = $db->fetchAll($compiled->sql(), $compiled->params());
```

`$db` is a host placeholder. The compiler returns PostgreSQL and bindings only. When a definition has an optional
count plan, the application executes it separately through the same adapter.

## 4. Restrict runtime scope

Actor, tenant, area, and policy services belong in project constraints through constructor/DI. Bind their values with
`SelectRuntimeContext::param()`:

```php
$select = $select->withRuntimeConstraint(
    static function (SelectRuntimeContext $context): SelectCondition {
        return SelectCondition::sql('o.area_id = ' . $context->param(77));
    }
);
```

In the generic select API, `param()` returns the complete placeholder including `:`. Class-string constraints use
`Select\Runtime\PsrContainerResolverInvoker` and `SelectRuntimeConstraintInterface`. Route authorization is separate.

## 5. Project the result

```php
$rows = (new SelectRowsProjector())->project($select, $databaseRows);
$items = (new SelectOptionsProjector())->project($options, $rows);
```

The generic projector retains declared aliases only and rejects rows missing an alias; explicit `null` remains valid.
The option projector then produces typed items such as:

```json
[{"value":42,"label":"Central organization","code":null}]
```

`OptionSourceDefinition::static()`, `preload()`, and `async()` describe frontend delivery metadata only. They do not
create a backend route/controller or replace `OptionListDefinition`.

## Integration diagnostics

| Symptom | Check |
| --- | --- |
| Validator rejects an integer criterion | Transport adapter explicitly converted the string to integer |
| Pagination cannot be added | Deterministic order was added first |
| A field disappears from output | Its alias exists in `SelectFieldDefinition` |
| Option projection fails | Role aliases match the declared `SelectValueType` |
| Scope predicate contains a raw value | Use the full placeholder returned by `SelectRuntimeContext::param()` |

Complete executable scenario: [`../examples/07-standalone-option-list.php`](../examples/07-standalone-option-list.php).
Supported symbols are listed in the shared [`../public-api.md`](../public-api.md).
