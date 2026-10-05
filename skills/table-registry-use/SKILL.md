---
name: table-registry-use
description: "Build or modify integrations with romanfedorskij/table-registry: table definitions, PostgreSQL query compilation, query validation, row projection, runtime constraints, resolver wiring, and XLSX export. Use for application code that consumes TableRegistry; do not use merely to install the package."
---

# Use TableRegistry

Treat the installed package as the API source of truth. Check the resolved version and inspect
`vendor/romanfedorskij/table-registry/docs/public-api.md`, its `README.md`, and `docs/examples/` when an API detail may
have drifted. In the library repository, use the corresponding root documents. Depend only on symbols listed in
`docs/public-api.md` and marked `@api`; autoloadable symbols marked `@internal` are not extension points.

TableRegistry describes tabular and standalone read models, compiles PostgreSQL SQL and parameters, projects database
rows into stable table/option payloads, and can write XLSX files. It does not execute SQL, choose a real connection,
authorize routes, define CRUD actions, or own the HTTP response. Keep those responsibilities in the application.

## Build A Standalone Select Or Option List

Use `SelectDefinition` for options, reusable lists, and small read models that do not need table metadata. Construct it
from `SelectSourceDefinition` and a non-empty ordered list of `SelectFieldDefinition`. Declare every user-driven
capability up front: criteria with fixed operators/types, literal contains search, allowlisted sorts, deterministic
order, and optional limit/offset pagination. Paginated definitions must add stable order before pagination.

Wrap a select in `OptionListDefinition` when the output has `value`/`label` semantics. Map required aliases explicitly,
choose string or integer value conversion, and declare optional `code`, `description`, and flat scalar `meta`. Keep
`OptionSourceDefinition::static/preload/async()` separate: it describes frontend delivery metadata, not backend SQL.

```php
$select = SelectDefinition::from(
    SelectSourceDefinition::relation('public.organization', 'o'),
    [
        SelectFieldDefinition::column('o.id', 'value'),
        SelectFieldDefinition::column('o.name', 'label'),
    ]
)
    ->withSearch(SelectSearchDefinition::contains(['o.name']))
    ->withOrderBy(SelectOrderDefinition::ascending('o.name'))
    ->withOrderBy(SelectOrderDefinition::ascending('o.id'))
    ->withPagination(SelectPaginationDefinition::limit(25, 100));

$options = OptionListDefinition::from(
    $select,
    valueAlias: 'value',
    labelAlias: 'label',
    valueType: SelectValueType::Integer
);
```

Transport parsing belongs to the application. Convert request aliases and scalar strings into a strict
`SelectQueryInput`, validate it with `SelectQueryValidator`, then compile it. Never place SQL, operators, callbacks,
request objects, actors, or services in query input.

```php
$query = SelectQueryInput::from(search: $search, criteria: ['areaId' => $areaId], limit: 25);
(new SelectQueryValidator())->validate($select, $query)->assertValid();
$compiled = $compiler->compile($select, $query);

// Execute with the project adapter, then keep only declared aliases.
$rows = (new SelectRowsProjector())->project($select, $databaseRows);
$items = (new SelectOptionsProjector())->project($options, $rows);
```

Use a `SelectRuntimeConstraintInterface` class resolved through `PsrContainerResolverInvoker` for actor, tenant, area,
or policy-dependent predicates. Inject project services through its constructor. Bind dynamic values only with
`SelectRuntimeContext::param()`, which returns the complete placeholder (`:p0`). Route-level authorization remains
separate. For selected-value hydration, declare an explicit `oneOf` criterion instead of bypassing the definition.

## Define The Table

Build a `TableRegistryDefinition` with a stable `registryKey`, a title, one `RegistrySourceDefinition`, and columns.
Every definition must contain exactly one `PrimaryKeyColumnRegistryDefinition`, and column keys must be unique.

Use developer-controlled SQL only in `from()`, joins, `ColumnSelectDefinition::sqlExpression()`, sort expressions, and
count sources. Never concatenate request values into those strings. Query values belong in filters or runtime
constraints so the compiler binds them as parameters.

Choose column behavior deliberately:

- A normal column is exposed in definition JSON and selected from SQL unless marked output-only.
- `withPayloadOnly()` / `withActionOnly()` keeps a technical value in projected rows but hides it from definition JSON
  and export choices.
- `withOutputOnly()` describes a value the application adds after SQL and before projection. It is not selected and is
  not searchable, sortable, or filterable. Export must be enabled explicitly if required.
- `withExportable(false)` keeps a visible/queryable column out of XLSX choices.
- `withRowTypeObject()`, `withHydration()`, and `withLabel()` affect projection, not SQL execution.

The compiler exposes each main select under the definition's column key; keep its declared alias aligned with that key
for clarity. Use unique auxiliary aliases only for extra raw values needed by a hydrator or label resolver; they are
not public table columns.

`RegistrySourceDefinition::withConnection()` and `withDataScope()` carry opaque application metadata. Resolve them in
the application's connection/data-scope layer; the SQL compiler does not open or switch connections.

## Handle A Rows Request

For untrusted HTTP query input, keep one normalized input across rows, count, and response serialization:

```php
$validation = (new RegistryQueryValidator())->validate($definition, $rawQuery);
$validation->assertValid();

$query = RegistrySqlQueryInput::fromArray($definition, $rawQuery);
$compiler = new PostgresRegistrySqlCompiler();

$rowsSql = $compiler->compileRows($definition, $query);
$countSql = $compiler->compileCount($definition, $query);

// Execute sql() with params() through the application's PostgreSQL adapter.
$projectedRows = (new RegistryRowsProjector())->project($definition, $databaseRows);
$result = new RegistryRowsResult($definition, $query, $total, $projectedRows);
```

Map validation issues to the application's normal client-error response. Validation is separate from compilation;
compiling a raw array does not implicitly reject unknown keys or malformed values. Execute `CompiledRegistrySql::sql()`
with `params()` through prepared/bound parameters. `sqlWithInlinedParams()` is diagnostic output, not an execution API.

Return `TableRegistryDefinition` JSON where the frontend needs table metadata. `RegistryRowsResult` is the rows wrapper
and intentionally contains `registryKey`, normalized query state, `total`, and projected rows rather than duplicating
the definition.

## Runtime Constraints And Resolvers

Use `withRuntimeConstraint()` for tenant, department, actor, or other request-context restrictions that must apply to
rows, count, and export. Bind every dynamic value with `RegistrySqlRuntimeContext::param()` (or explicit condition
params); return `RegistrySqlCondition::impossible()` when the actor has no allowed scope.

Callbacks work with the native resolver invoker. When definitions contain class-string constraints, hydrators, or
label resolvers managed by DI, construct both the compiler and projector with the same
`PsrContainerRegistryResolverInvoker`. Resolver classes must implement the matching TableRegistry interface.

Route-level authorization still belongs to the application. A runtime constraint narrows data; it does not replace
permission checks for reaching an endpoint or invoking an action.

## Export XLSX

Use the same validated filters and runtime context as the rows endpoint:

1. Compile unpaginated data with `compileExportRows()` and execute it with bound params.
2. Project the database rows so hydration and labels match the screen.
3. Pass requested keys through `RegistryExportColumnResolver`; it enforces each column's export policy.
4. Flatten labels with `RegistryProjectedRowsFlattener::flattenLabels()`.
5. Pass the resolved columns and flattened rows to `RegistryXlsxExporter::export()`.

An empty requested-key list means all exportable columns. If the resolved list is empty, handle that before invoking
the exporter, which requires at least one column. The exporter returns a temporary file path and download filename;
the application must stream/move the file and clean up the temporary file according to its response lifecycle.

## Verification

Test the consumer integration at its real boundary. At minimum cover definition serialization, invalid query input,
rows/count using identical constraints, row labels/hydration, connection selection when non-default, export policy,
and the no-access runtime-constraint path. In this library repository, run `composer check` before handing work off.

For a `v0.5.x` consumer upgrade, follow `docs/migration-v0.5-to-v0.6.md`: pilot an exact RC, migrate one preload and
one scoped async endpoint, keep legacy tables working, then move to `^0.6.0` only after the project checks pass.
