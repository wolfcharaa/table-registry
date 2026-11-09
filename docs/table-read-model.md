# Table Read-Model

Это руководство проводит один supported path: от `TableRegistryDefinition` и недоверенного query input до
скомпилированного PostgreSQL, projected rows и стабильного response payload. Оно не задаёт HTTP framework или DB
adapter — эти части принадлежат приложению.

Полные исполняемые варианты находятся в [`examples/`](examples/README.md). Фрагменты ниже предполагают, что
`$definition` уже создан на предыдущем шаге.

## 1. Опишите Таблицу

Definition содержит стабильный `registryKey`, title, source и колонки. Ровно одна колонка должна быть
`PrimaryKeyColumnRegistryDefinition`; ключи колонок должны быть уникальными.

```php
use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\ColumnSelectDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\TextFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Sort\RegistryDefaultSortDefinition;
use Wolfcharaa\TableRegistry\Sort\RegistrySortDefinition;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;

$definition = (new TableRegistryDefinition(
    registryKey: 'users',
    title: 'Пользователи',
    source: RegistrySourceDefinition::from('public.users u'),
    columns: [
        (new PrimaryKeyColumnRegistryDefinition('id'))
            ->withSelect(ColumnSelectDefinition::sqlExpression('id', 'u.id')),
        (new ColumnRegistryDefinition('email', 'Email'))
            ->withSelect(ColumnSelectDefinition::sqlExpression('email', 'u.email'))
            ->withSearchable()
            ->withSort(RegistrySortDefinition::byColumn('email'))
            ->withFilter(TextFilterRegistryDefinition::contains(
                queryKey: 'filter_email',
                columnKey: 'email',
                title: 'Email'
            )),
    ]
))->withDefaultSort(RegistryDefaultSortDefinition::desc('id'));
```

Поддерживаемый расширенный пример —
[`examples/01-table-definition.php`](examples/01-table-definition.php).

### Source И SQL-Фрагменты

`RegistrySourceDefinition` хранит `FROM`, optional joins, logical connection/data scope и optional count source.
Compiler не открывает connection и не проверяет доступность relation.

```php
$source = RegistrySourceDefinition::from('public.orders o')
    ->withLeftJoin('public.users u', 'u.id = o.user_id')
    ->withConnection('warehouse')
    ->withCountSource('public.orders o');
```

`from()`, joins, select/sort expressions и count source являются developer-controlled SQL. Не подставляйте в них
request values. Динамические значения должны проходить через query input, filters или runtime constraints и попадать
в bound parameters.

`withConnection()` и data scope — opaque metadata. Приложение само сопоставляет их реальному DB adapter. Отдельный
count source обязан сохранять aliases и поля, на которые ссылаются filters, search и constraints.

### Режимы Колонок

| Режим | Definition JSON | SQL `SELECT` | Rows payload | Export по умолчанию |
| --- | --- | --- | --- | --- |
| Обычная колонка | да | да | да | да |
| `withPayloadOnly()` / `withActionOnly()` | нет | да | да | нет |
| `withOutputOnly()` | да | нет | да, из подготовленной приложением строки | нет |
| `withExportable(false)` | без изменений | без изменений | без изменений | нет |

Output-only колонка не может участвовать в search, sort или filter. Export для неё можно включить явно через
`withExportable(true)`, если приложение добавляет значение до projection. Primary key нельзя сделать payload-only или
output-only.

Если `withSelect()` не указан, compiler использует quoted identifier с ключом колонки. Сложное выражение задавайте
через `ColumnSelectDefinition::sqlExpression()`. Auxiliary selects предназначены только для hydrator/label resolver и
не становятся отдельными public columns.

Встроенные filter definitions покрывают text, integer, boolean, nullability, date/time ranges и static/preload/async
select. Search включается только через `withSearchable()`, sort — через `withSort()`.

## 2. Отдайте Frontend Definition

`TableRegistryDefinition` реализует `JsonSerializable`:

```php
$frontendContract = $definition->jsonSerialize();
```

Payload содержит `schemaVersion`, `registryKey`, `title`, `primaryKey`, exposed columns и query metadata. Backend-only
source, joins, SQL expressions, constraints, hydrators и label resolvers в JSON не попадают.

Минимальный результат можно увидеть в
[`examples/00-quick-start.php`](examples/00-quick-start.php). Полный JSON фиксируется contract и characterization
tests; не собирайте параллельную frontend-схему вручную.

## 3. Провалидируйте Query До Компиляции

Transport adapter приложения сначала превращает HTTP/CLI input в массив со значениями, допустимыми для definition.
Перед SQL compilation проверьте его явно:

```php
use Wolfcharaa\TableRegistry\Query\RegistryQueryValidator;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlQueryInput;

$rawQuery = [
    'search' => 'example.org',
    'sort_col' => 'email',
    'sort_dir' => 'asc',
    'page' => '1',
    'per_page' => '25',
];

$validation = (new RegistryQueryValidator())->validate($definition, $rawQuery);
$validation->assertValid();

$query = RegistrySqlQueryInput::fromArray($definition, $rawQuery);
```

Validator сообщает об unknown keys, неизвестных или несортируемых колонках, direction, pagination limits, invalid
filter/range values и недопустимом search. Compilation массива сама по себе не включает strict validation: не
пропускайте этот шаг для недоверенного input.

Преобразование validation issues в HTTP client error остаётся ответственностью приложения.

## 4. Скомпилируйте Rows И Count

```php
use Wolfcharaa\TableRegistry\Sql\PostgresRegistrySqlCompiler;

$compiler = new PostgresRegistrySqlCompiler();
$rowsSql = $compiler->compileRows($definition, $query);
$countSql = $compiler->compileCount($definition, $query);

$rows = $db->fetchAll($rowsSql->sql(), $rowsSql->params());
$total = $db->fetchScalar($countSql->sql(), $countSql->params());
```

`$db` — application-owned placeholder, не API библиотеки. Выполняйте `sql()` с `params()` через prepared/bound
parameters. `sqlWithInlinedParams()` предназначен только для диагностики.

`compileRows()` применяет pagination; `compileCount()` считает total; `compileExportRows()` строит тот же filtered
dataset без pagination. Поддерживаемый сценарий с ожидаемым SQL находится в
[`examples/02-postgres-sql-compiler.php`](examples/02-postgres-sql-compiler.php).

## 5. Спроектируйте Raw Rows

```php
use Wolfcharaa\TableRegistry\Row\RegistryRowsProjector;
use Wolfcharaa\TableRegistry\Row\RegistryRowsResult;

$projectedRows = (new RegistryRowsProjector())->project($definition, $rows);

$result = new RegistryRowsResult(
    definition: $definition,
    query: $query,
    total: $total,
    rows: $projectedRows
);

$responsePayload = $result->jsonSerialize();
```

Projector выдаёт `{primaryKey, values}` в порядке definition. Каждое значение имеет `value` и строковый `label`.
`withRowTypeObject()`, `withHydration()` и `withLabel()` влияют на projection, но не выполняют SQL. Для class-string
resolvers из DI передайте один `PsrContainerRegistryResolverInvoker` compiler-у и projector-у.

Исполняемые варианты: [row projection](examples/03-row-projection.php) и
[PSR container invoker](examples/06-psr-container-invoker.php).

## 6. Добавьте Runtime Scope

Runtime constraints нужны для actor-, tenant-, department- или policy-dependent ограничений, которые должны одинаково
действовать на rows, count и export:

```php
use Wolfcharaa\TableRegistry\Sql\RegistrySqlCondition;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlRuntimeContext;

$definition = $definition->withRuntimeConstraint(
    static function (RegistrySqlRuntimeContext $context): RegistrySqlCondition {
        $department = $context->param(42);

        return RegistrySqlCondition::raw('u.department_id = :' . $department);
    }
);
```

Legacy `RegistrySqlRuntimeContext::param()` возвращает имя параметра без `:`. Для отсутствующего scope верните
`RegistrySqlCondition::impossible()`. Route-level authorization всё равно должна выполняться отдельно.

Для project services используйте class-string, реализующий `RegistrySqlRuntimeConstraintInterface`, и
`PsrContainerRegistryResolverInvoker`. Не передавайте actor или service objects через query input.

Полный пример: [`examples/04-runtime-constraint.php`](examples/04-runtime-constraint.php).

## 7. Экспортируйте Тот Же Dataset

Используйте тот же validated query и runtime scope, что и для rows:

1. Получите данные через `compileExportRows()` и application DB adapter.
2. Спроектируйте rows тем же `RegistryRowsProjector`.
3. Ограничьте requested columns через `RegistryExportColumnResolver`.
4. Преобразуйте labels через `RegistryProjectedRowsFlattener::flattenLabels()`.
5. Передайте columns и flat rows в `RegistryXlsxExporter::export()`.

Пустой список requested keys означает все exportable columns. Если после policy не осталось ни одной колонки,
остановитесь до exporter-а. Результат содержит временный file path и download filename; приложение отвечает за
stream/move и удаление файла в своём response lifecycle.

Поддерживаемый пример: [`examples/05-xlsx-export.php`](examples/05-xlsx-export.php).

## Диагностика Интеграции

| Симптом | Проверка |
| --- | --- |
| Unknown query key или invalid sort/filter | Сначала `RegistryQueryValidator`, затем `RegistrySqlQueryInput` |
| Rows и count расходятся | Используются ли одинаковые definition, query и runtime constraints |
| Колонка отсутствует в JSON, но есть в row | Не включён ли `withPayloadOnly()` |
| Колонка отсутствует в SQL, но ожидается в row | Не включён ли `withOutputOnly()`; добавило ли её приложение до projection |
| Resolver class не вызывается | Передан ли PSR-container invoker и реализован ли нужный interface |
| В export попали не те поля | Применён ли `RegistryExportColumnResolver`, а не raw requested keys |

Точный compatibility promise symbols находится в [`public-api.md`](public-api.md).
