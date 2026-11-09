# Standalone Select И Option Lists

[English](en/select-and-options.md)

`SelectDefinition` описывает read-model без `TableRegistryDefinition`, primary key и frontend table metadata. Он
подходит для preload/async options, зависимых списков, восстановления выбранных значений и небольших project-specific
reads.

Transport parsing, SQL execution, authorization, route и HTTP envelope остаются в приложении.

## 1. Опишите Select

```php
use Wolfcharaa\TableRegistry\Option\OptionListDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectCriterionDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectFieldDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectOrderDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectPaginationDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectSearchDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectSourceDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectValueType;

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

Declare every user-controlled capability in advance: criterion operator/type, search expressions, allowed sorts and
pagination. Paginated definition должен иметь deterministic order до `withPagination()`.

`OptionListDefinition` отображает aliases select-а в stable `value`/`label` и optional `code`, `description`, `meta`.
Required aliases задаются явно; value преобразуется в объявленный string или integer type.

## 2. Соберите Canonical Input

Приложение преобразует HTTP/CLI aliases и scalar strings в строгий `SelectQueryInput`. Библиотека намеренно не читает
request и не преобразует строки в числа неявно.

```php
use Wolfcharaa\TableRegistry\Select\Query\SelectQueryInput;
use Wolfcharaa\TableRegistry\Select\Query\SelectQueryValidator;

$query = SelectQueryInput::from(
    search: 'центр',
    criteria: ['departmentId' => 10],
    limit: 25
);

(new SelectQueryValidator())->validate($select, $query)->assertValid();
```

Query input не должен содержать SQL, operators, callbacks, request objects, actors или services. Для восстановления
выбранных значений объявите отдельный `oneOf` criterion вместо обхода definition.

## 3. Скомпилируйте И Выполните SQL

```php
use Wolfcharaa\TableRegistry\Select\Sql\PostgresSelectCompiler;

$compiled = (new PostgresSelectCompiler())->compile($select, $query);
$databaseRows = $db->fetchAll($compiled->sql(), $compiled->params());
```

`$db` — placeholder host application. Compiler возвращает только PostgreSQL и bindings. Если definition содержит
optional count plan, application выполняет его отдельно через тот же DB adapter.

## 4. Ограничьте Runtime Scope

Current actor, tenant, area и policy services не передаются через универсальный context bag. Project constraint
получает services через constructor/DI, а значения связывает через `SelectRuntimeContext::param()`:

```php
use Wolfcharaa\TableRegistry\Select\Runtime\SelectRuntimeContext;
use Wolfcharaa\TableRegistry\Select\Sql\SelectCondition;

$select = $select->withRuntimeConstraint(
    static function (SelectRuntimeContext $context): SelectCondition {
        return SelectCondition::sql('o.area_id = ' . $context->param(77));
    }
);
```

В generic select API `param()` возвращает полный placeholder с `:`. Constraints выполняются после declarative
criteria в порядке регистрации. Для class-string constraints используйте `Select\Runtime\PsrContainerResolverInvoker`
и interface `SelectRuntimeConstraintInterface`. Route authorization остаётся отдельной проверкой.

## 5. Спроектируйте Результат

```php
use Wolfcharaa\TableRegistry\Option\SelectOptionsProjector;
use Wolfcharaa\TableRegistry\Select\Row\SelectRowsProjector;

$rows = (new SelectRowsProjector())->project($select, $databaseRows);
$items = (new SelectOptionsProjector())->project($options, $rows);
```

Generic projector сохраняет только aliases из definition и отклоняет строку, в которой отсутствует declared alias;
`null` при этом остаётся допустимым значением. Option projector затем создаёт typed payload:

```json
[{"value":42,"label":"Центральная организация","code":null}]
```

`OptionSourceDefinition::static()`, `preload()` и `async()` описывает только frontend delivery metadata. Он не связан
с backend `OptionListDefinition` и не создаёт route/controller.

## Диагностика Интеграции

| Симптом | Проверка |
| --- | --- |
| Validator отклоняет integer criterion | Выполнил ли transport adapter явное преобразование строки в integer |
| Pagination не создаётся | Добавлен ли deterministic order до pagination |
| В output пропало поле | Объявлен ли его alias в `SelectFieldDefinition` |
| Option projection падает | Совпадают ли role aliases и объявленный `SelectValueType` |
| Scope predicate содержит raw value | Используется ли полный placeholder из `SelectRuntimeContext::param()` |

Полный исполняемый сценарий: [`examples/07-standalone-option-list.php`](examples/07-standalone-option-list.php).
Поддерживаемые symbols перечислены в [`public-api.md`](public-api.md).
