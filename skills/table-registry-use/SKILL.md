---
name: table-registry-use
description: "Build or modify integrations with romanfedorskij/table-registry: table definitions, PostgreSQL query compilation, query validation, row projection, runtime constraints, resolver wiring, and XLSX export. Use for application code that consumes TableRegistry; do not use merely to install the package."
---

# Use TableRegistry

Treat the installed package as the API source of truth. Check the resolved version and inspect
`vendor/romanfedorskij/table-registry/README.md`, its `docs/examples/`, and relevant source when an API detail may have
drifted. In the library repository, use the root `README.md`, `docs/examples/`, and `src/` instead.

TableRegistry describes a tabular read model, compiles PostgreSQL SQL and parameters, projects database rows into a
stable payload, and can write XLSX files. It does not execute SQL, choose a real connection, authorize routes, define
CRUD actions, or own the HTTP response. Keep those responsibilities in the application.

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
and the no-access runtime-constraint path. In this library repository, run `composer test`.
