# Table read model

[Русский](../table-read-model.md)

This guide follows the supported path from a `TableRegistryDefinition` and untrusted query input to compiled
PostgreSQL, projected rows, and a stable response payload. HTTP frameworks and DB adapters remain application-owned.
Code fragments below are partial and assume imports/bootstrap from the linked executable examples.

## 1. Obtain a definition

For static shapes in `v0.7`, use relation/aggregate Attributes and `AttributeTableRegistryCompiler`; see
[`attributes.md`](attributes.md) and [`../examples/08-attribute-table.php`](../examples/08-attribute-table.php). Keep
the object API for runtime-dependent shapes and incremental migration. Both frontends emit one
`TableRegistryDefinition`, so every following step is shared.

A definition has a stable registry key, title, source, and unique columns with exactly one visible primary key.
Developer-controlled sources, joins, select/sort expressions, and count sources must never contain request values.
Dynamic values go through query input, filters, or runtime constraints and become bound parameters.

`RegistrySourceDefinition` stores the `FROM` fragment, optional joins, logical connection/data scope, and optional
count source. The compiler neither opens the connection nor checks that a physical relation exists. The application
maps connection/data-scope metadata to its DB adapter. A separate count source must preserve aliases and fields used by
filters, search, and constraints.

Column modes:

| Mode | Definition JSON | SQL select | Rows payload | Default export |
| --- | --- | --- | --- | --- |
| Visible | yes | yes | yes | yes |
| Payload | no | yes | yes | no |
| Output | yes | no | yes, from an application-prepared row | no |

Output-only columns cannot search, sort, or filter. They may be explicitly exportable when the application adds the
value before projection. A primary key cannot be payload-only or output-only. Without `withSelect()`, the compiler
selects a quoted identifier based on the column key; use `ColumnSelectDefinition::sqlExpression()` for advanced SQL.
Auxiliary selects are raw inputs for hydration/labels, not public columns. Attribute-authored definitions represent
the same role with `Internal` properties.

Built-in filters cover text, integer, boolean, nullability, date/time ranges, and static/preload/async selects. Search
requires `withSearchable()` and sort requires `withSort()`.

## 2. Return the frontend definition

`TableRegistryDefinition::jsonSerialize()` returns schema version, registry key, title, primary key, exposed columns,
and query metadata. Sources, SQL expressions, constraints, hydrators, and label resolvers remain backend-only.
Do not maintain a second frontend schema by hand; definition serialization is the contract.

## 3. Validate query input

```php
$validation = (new RegistryQueryValidator())->validate($definition, $rawQuery);
$validation->assertValid();
$query = RegistrySqlQueryInput::fromArray($definition, $rawQuery);
```

Validation is deliberately separate from compilation. Map validation issues to the application's normal client-error
response before compiling untrusted input. The validator reports unknown keys, unavailable/non-sortable columns,
direction, pagination limits, filter/range values, and invalid search input.

## 4. Compile and execute rows/count

```php
$compiler = new PostgresRegistrySqlCompiler();
$rowsSql = $compiler->compileRows($definition, $query);
$countSql = $compiler->compileCount($definition, $query);

$rows = $db->fetchAll($rowsSql->sql(), $rowsSql->params());
$total = $db->fetchScalar($countSql->sql(), $countSql->params());
```

`$db` is an application placeholder. Execute `sql()` with `params()` through prepared/bound parameters;
`sqlWithInlinedParams()` is diagnostic output only. `compileExportRows()` uses the same filtered dataset without
pagination. `compileRows()` applies pagination and `compileCount()` computes the total; all three use the same runtime
constraints.

## 5. Project raw rows

```php
$projected = (new RegistryRowsProjector())->project($definition, $rows);
$result = new RegistryRowsResult($definition, $query, $total, $projected);
$responsePayload = $result->jsonSerialize();
```

Projection creates `{primaryKey, values}` and applies row value objects, hydration, and labels. Use one
`PsrContainerRegistryResolverInvoker` for DI-managed class-string runtime resolvers.

## 6. Add request scope

Use immutable `withRuntimeConstraint()` for actor, tenant, department, or policy restrictions shared by rows, count,
and export. Bind dynamic values through `RegistrySqlRuntimeContext::param()` and keep route authorization separate.
The Attribute compiler itself receives no request context.

```php
$definition = $definition->withRuntimeConstraint(
    static function (RegistrySqlRuntimeContext $context): RegistrySqlCondition {
        $department = $context->param(42);

        return RegistrySqlCondition::raw('u.department_id = :' . $department);
    }
);
```

The legacy registry context returns a parameter name without `:`. Return `RegistrySqlCondition::impossible()` for an
empty allowed scope. Use a class implementing `RegistrySqlRuntimeConstraintInterface` plus
`PsrContainerRegistryResolverInvoker` when the constraint needs project services.

## 7. Export the same dataset

Compile with `compileExportRows()`, project through the same projector, enforce requested keys with
`RegistryExportColumnResolver`, flatten labels, and write with `RegistryXlsxExporter`. The application owns streaming
and cleanup of the returned temporary file.

An empty requested-key list means every exportable column. Stop before invoking the exporter if policy leaves no
columns. Use the same validated query and runtime scope as the screen so export cannot silently widen the dataset.

## Integration diagnostics

| Symptom | Check |
| --- | --- |
| Unknown query key or invalid sort/filter | Run `RegistryQueryValidator`, then build `RegistrySqlQueryInput` |
| Rows and count disagree | Use the same definition, query, and runtime constraints |
| Column is missing from JSON but present in rows | Check payload-only mode |
| Column is missing from SQL but expected in rows | Check output-only mode and application row preparation |
| Resolver class is not invoked | Supply the PSR-container invoker and implement the matching interface |
| Export contains the wrong fields | Apply `RegistryExportColumnResolver`, not raw requested keys |

Runnable evidence is indexed in [`examples/README.md`](examples/README.md); exact supported symbols are in the shared
[`../public-api.md`](../public-api.md).
