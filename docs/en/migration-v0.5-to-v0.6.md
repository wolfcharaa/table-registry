# Migration `v0.5.x` → `v0.6.0`

[Русский](../migration-v0.5-to-v0.6.md)

`v0.6.0` adds the standalone select/option API while retaining the existing table registry API. You can migrate one
endpoint at a time: updating the dependency does not require an immediate rewrite of working tables.

## Versions and upgrade order

The source release baseline is `v0.5.2`. The final `v0.6.0` release is available, so a new project pilot should install
the stable minor line rather than the historical release candidate:

```bash
composer require romanfedorskij/table-registry:"^0.6.0" --with-all-dependencies
composer show romanfedorskij/table-registry
composer check-platform-reqs
```

If the target is `v0.7.x`, finish this legacy table API check first and then follow the
[`v0.6.x` → `v0.7.0` migration](migration-v0.6-to-v0.7.md). This separates dependency/API regressions from moving
individual definitions to Attributes.

The minimum PHP version for `v0.6.x` is `8.2`. Do not use `--ignore-platform-reqs` or edit `composer.lock` manually.
If the application synchronizes bundled skills through `llm/skills`, refresh them from the resolved package version
after the Composer update:

```bash
composer skills:update romanfedorskij/table-registry
```

## What remains compatible

- `TableRegistryDefinition`, columns, filters, rows result, and XLSX APIs remain public.
- Existing table/filter JSON payloads are preserved.
- `PostgresRegistrySqlCompiler` and the legacy query/runtime contracts continue to work.
- `SelectFilterRegistryDefinition::static/preload/async()` preserves its JSON shape while using the shared
  `OptionSourceDefinition` internally.
- There are no immediate deprecations or mandatory bulk table rewrites.

Do not mechanically replace an existing table API with the generic select API. The new API primarily serves options,
reusable lists, and small read models that previously assembled SQL by hand.

## Recommended project structure

For each reusable list, keep business identity in a project wrapper and SQL/query semantics in library definitions:

```php
<?php

declare(strict_types=1);

use Wolfcharaa\TableRegistry\Option\OptionListDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectCriterionDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectFieldDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectOrderDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectPaginationDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectSearchDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectSourceDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectValueType;

final class AvailableOrganizationOptions
{
    public function definition(): OptionListDefinition
    {
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
            ->withCriterion(SelectCriterionDefinition::oneOf(
                'selectedIds',
                'o.id',
                SelectValueType::Integer
            ))
            ->withSearch(SelectSearchDefinition::contains(['o.name', 'o.code']))
            ->withOrderBy(SelectOrderDefinition::ascending('o.name'))
            ->withOrderBy(SelectOrderDefinition::ascending('o.id'))
            ->withPagination(SelectPaginationDefinition::limit(25, 100))
            ->withRuntimeConstraint(AvailableOrganizationScope::class);

        return OptionListDefinition::from(
            $select,
            valueAlias: 'value',
            labelAlias: 'label',
            valueType: SelectValueType::Integer
        )->withCode('code');
    }
}
```

The wrapper can be reused by an option controller, form definition, or table-specific orchestration without turning
`SelectDefinition` into a named catalog entity inside the library.

## Runtime context

Move actor, tenant, or area restrictions out of repeated controller SQL and into a DI-resolved constraint:

```php
use Wolfcharaa\TableRegistry\Select\Runtime\SelectRuntimeConstraintInterface;
use Wolfcharaa\TableRegistry\Select\Runtime\SelectRuntimeContext;
use Wolfcharaa\TableRegistry\Select\Sql\SelectCondition;

final class AvailableOrganizationScope implements SelectRuntimeConstraintInterface
{
    public function __construct(private CurrentArea $currentArea)
    {
    }

    public function __invoke(SelectRuntimeContext $context): SelectCondition
    {
        return SelectCondition::sql(
            'o.area_id = ' . $context->param($this->currentArea->id())
        );
    }
}
```

Connect the project PSR container through `Select\Runtime\PsrContainerResolverInvoker`. Do not put a request, actor,
container, or services into `SelectQueryInput`; the input contains canonical search/sort/limit/offset/criteria only.

## Transport adapter and execution

The application adapter owns aliases and strict transport-value normalization. This partial snippet starts after the
adapter has produced typed values and the application has composed its reader/compiler services:

```php
$criteria = ['departmentId' => $departmentId];
if ($selectedIds !== []) {
    $criteria['selectedIds'] = $selectedIds;
}

$query = SelectQueryInput::from(
    search: $normalizedSearch,
    criteria: $criteria,
    limit: $limit,
    offset: $offset
);

$validation = (new SelectQueryValidator())->validate($options->select(), $query);
$validation->assertValid();

$compiled = $compiler->compile($options->select(), $query);
$rawRows = $projectReader->fetchAll(
    connection: $options->select()->source()->connection(),
    sql: $compiled->sql(),
    params: $compiled->params()
);

$rows = (new SelectRowsProjector())->project($options->select(), $rawRows);
$items = (new SelectOptionsProjector())->project($options, $rows);
```

`projectReader`, transaction policy, route authorization, and the HTTP response envelope remain application-owned.
Do not introduce a generic endpoint/catalog solely for the new API; keep explicit one-source controllers.

## Generic and legacy runtime differences

| `v0.5.x` registry | `v0.6.0` standalone select |
| --- | --- |
| `RegistrySqlQueryInput` accepts a table-shaped raw array | `SelectQueryInput` accepts canonical typed values |
| `RegistrySqlRuntimeContext::param()` returns the `p0` key | `SelectRuntimeContext::param()` returns the `:p0` placeholder |
| Table page/per-page | Generic limit/offset |
| Column filters choose an operator | A criterion definition fixes its operator and value type |
| Rows projection creates table `{primaryKey, values}` | Generic projection creates an alias-keyed `SelectRow` |
| Select filter describes the frontend source | `OptionListDefinition` describes backend items; `OptionSourceDefinition` describes frontend delivery |

Do not copy a legacy constraint callback without checking placeholder syntax. Adding another colon before the generic
`param()` result produces an invalid `::p0` placeholder.

## Verification checklist for `change-version-migrate`

- [ ] The source version `v0.5.2` and rollback command are recorded.
- [ ] Stable line `^0.6.0` is installed without `--ignore-platform-reqs`.
- [ ] `composer show` and `composer check-platform-reqs` confirm PHP 8.2+ and compatible dependencies.
- [ ] Bundled `table-registry-install` and `table-registry-use` skills are synchronized.
- [ ] Transport aliases/coercion remain in the application adapter.
- [ ] Search, criteria, sorting, and pagination pass `SelectQueryValidator`.
- [ ] Actor/area/tenant scope is implemented as a runtime constraint and has a no-access test.
- [ ] SQL executes only with `CompiledSelect::params()` through prepared/bound parameters.
- [ ] Raw rows pass through `SelectRowsProjector`; option rows pass through `SelectOptionsProjector`.
- [ ] A preload endpoint preserves its route and JSON item shape.
- [ ] An async endpoint preserves its search alias, limits, deterministic order, authorization, and scope.
- [ ] Selected IDs can be hydrated through a separately declared `oneOf` criterion.
- [ ] Existing table JSON/rows/count/export regression tests remain green.
- [ ] The project test suite and static analysis pass before migrating the remaining endpoints.

## Rollback

If the project pilot is incompatible, roll back only the dependency constraint and lock file through Composer:

```bash
composer require romanfedorskij/table-registry:"0.5.2" --with-all-dependencies
composer show romanfedorskij/table-registry
composer check-platform-reqs
```

Do not keep project code that imports `Select\*` or `Option\*` after rolling back to `v0.5.2`; revert the endpoint
migration commit together with the Composer change.
