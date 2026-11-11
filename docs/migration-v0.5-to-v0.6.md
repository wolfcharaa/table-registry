# Migration `v0.5.x` → `v0.6.0`

[English](en/migration-v0.5-to-v0.6.md)

`v0.6.0` добавляет standalone select/option API и сохраняет существующий table registry API. Миграцию можно проводить
по одному endpoint-у: обновление зависимости само по себе не требует немедленно переписывать действующие таблицы.

## Версии И Порядок Обновления

Исходная release baseline — `v0.5.2`. Final release `v0.6.0` уже опубликован, поэтому новый project pilot
должен устанавливать stable minor line, а не исторический release candidate:

```bash
composer require romanfedorskij/table-registry:"^0.6.0" --with-all-dependencies
composer show romanfedorskij/table-registry
composer check-platform-reqs
```

Если целевая версия — `v0.7.x`, сначала завершите эту проверку legacy table API, затем пройдите
[миграцию `v0.6.x` → `v0.7.0`](migration-v0.6-to-v0.7.md). Так разделяются dependency/API regression и перевод
конкретных definitions на Attributes.

Минимальная версия PHP для `v0.6.x` — `8.2`. Не используйте `--ignore-platform-reqs` и не редактируйте
`composer.lock` вручную. Если приложение синхронизирует bundled skills через `llm/skills`, после Composer update
обновите их из разрешённой версии пакета:

```bash
composer skills:update romanfedorskij/table-registry
```

## Что Остаётся Совместимым

- `TableRegistryDefinition`, columns, filters, rows result и XLSX API остаются публичными.
- Существующие table/filter JSON payload сохраняются.
- `PostgresRegistrySqlCompiler` и legacy query/runtime contracts продолжают работать.
- `SelectFilterRegistryDefinition::static/preload/async()` сохраняет прежнюю JSON shape, но внутри использует общий
  `OptionSourceDefinition`.
- Немедленных deprecations или обязательного массового переписывания таблиц нет.

Не заменяйте действующий table API generic select-ом механически. Новый API предназначен прежде всего для options,
повторяющихся списков и небольших read-моделей, которые раньше собирали SQL вручную.

## Рекомендуемая Структура В Проекте

Для каждого переиспользуемого списка оставьте business identity в project wrapper, а SQL/query semantics — в
library definitions:

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

Такой wrapper может использоваться option controller-ом, form definition или table-specific orchestration, не
делая `SelectDefinition` именованным catalog entity внутри библиотеки.

## Runtime Context

Перенесите actor/tenant/area restrictions из повторяющегося controller SQL в DI-resolved constraint:

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

Подключите project PSR container через `Select\Runtime\PsrContainerResolverInvoker`. Не помещайте request, actor,
container или services в `SelectQueryInput`: input содержит только canonical search/sort/limit/offset/criteria.

## Transport Adapter И Выполнение

Application adapter отвечает за aliases и строгую нормализацию transport values. Этот частичный
фрагмент начинается после того, как adapter получил typed values, а приложение собрало reader/compiler
services:

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

`projectReader`, transaction policy, route authorization и HTTP response envelope остаются проектными. Не добавляйте
generic endpoint/catalog только ради нового API: сохраняйте explicit one-source controllers.

## Отличия Generic И Legacy Runtime API

| `v0.5.x` registry | `v0.6.0` standalone select |
|---|---|
| `RegistrySqlQueryInput` принимает table-shaped raw array | `SelectQueryInput` принимает уже canonical typed values |
| `RegistrySqlRuntimeContext::param()` возвращает key `p0` | `SelectRuntimeContext::param()` возвращает placeholder `:p0` |
| Table page/per-page | Generic limit/offset |
| Column filters выбирают operator | Criterion definition заранее фиксирует operator и value type |
| Rows projection создаёт table `{primaryKey, values}` | Generic projection создаёт alias-keyed `SelectRow` |
| Select filter описывает frontend source | `OptionListDefinition` описывает backend items, `OptionSourceDefinition` — frontend delivery |

Не переносите legacy constraint callback копированием без проверки placeholder syntax: дополнительное двоеточие перед
generic `param()` создаст ошибочный `::p0`.

## Verification Checklist Для `change-version-migrate`

- [ ] Зафиксирована исходная версия `v0.5.2` и сохранён rollback command.
- [ ] Stable line `^0.6.0` установлена без `--ignore-platform-reqs`.
- [ ] `composer show` и `composer check-platform-reqs` подтверждают PHP 8.2+ и разрешённые зависимости.
- [ ] Bundled `table-registry-install` и `table-registry-use` skills синхронизированы.
- [ ] Transport aliases/coercion остаются в application adapter.
- [ ] Search, criteria, sort и pagination проходят `SelectQueryValidator`.
- [ ] Actor/area/tenant scope реализован runtime constraint-ом и покрыт no-access test.
- [ ] SQL выполняется только с `CompiledSelect::params()` через prepared/bound parameters.
- [ ] Raw rows проходят `SelectRowsProjector`, option rows — `SelectOptionsProjector`.
- [ ] Preload endpoint сохраняет route и JSON item shape.
- [ ] Async endpoint сохраняет search alias, limits, deterministic order и authorization/scope.
- [ ] Выбранные IDs можно гидратировать отдельным declared `oneOf` criterion.
- [ ] Старые table JSON/rows/count/export regression tests остаются зелёными.
- [ ] Project test suite и статический анализ проходят до перевода остальных endpoints.

## Rollback

При несовместимости project pilot откатите только dependency constraint и lock-файл через Composer:

```bash
composer require romanfedorskij/table-registry:"0.5.2" --with-all-dependencies
composer show romanfedorskij/table-registry
composer check-platform-reqs
```

Не оставляйте project code, импортирующий `Select\*`/`Option\*`, при rollback на `v0.5.2`: откатывайте migration
commit endpoint-а вместе с Composer change.
