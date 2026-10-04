# TableRegistry

`TableRegistry` - PHP-библиотека для явного описания табличных read-моделей, сборки PostgreSQL-запросов,
нормализации строк под frontend-контракт и выгрузки данных в XLSX.

Библиотека не зависит от конкретного web-фреймворка, DI-контейнера или HTTP-слоя. Она описывает только то, что
backend должен знать о таблице: источник данных, колонки, фильтры, сортировки, поиск, пагинацию и подготовку строк.

## Возможности

- описание таблицы через PHP-классы без YAML/JSON-конфигурации;
- обязательная primary key колонка;
- явные sortable/filterable/searchable правила на уровне колонок;
- сериализация definition в стабильный JSON-контракт для frontend;
- компиляция rows/count/export SQL для PostgreSQL;
- validation query параметров относительно definition без привязки к HTTP;
- payload-only/action-only колонки для служебных значений строк без попадания в настройки отображения;
- output-only/computed колонки для значений, добавленных приложением до projection;
- export policy на уровне колонок для безопасного выбора XLSX-полей;
- runtime constraints через callback или invokable class;
- value object нормализация значений строк;
- label/hydration resolvers для человекочитаемого payload;
- serializable rows result contract для страницы таблицы;
- flatten projected rows для export/print;
- XLSX export через OpenSpout.

Библиотека не управляет действиями пользователя, кнопками, layout таблицы, проверками доступа на уровне маршрута,
CRUD-командами и HTTP-роутингом. Эти решения должны оставаться в приложении, которое использует библиотеку.

## Требования

- PHP 8.2 или выше;
- `ext-json`;
- `ext-mbstring`;
- `psr/container` `^1.1 || ^2.0`;
- `openspout/openspout` `^4.28 || ^5.0` для XLSX export;
- `testo/testo` `0.10.54` и `phpstan/phpstan` `2.2.16` только для разработки и проверок.

PHP 8.2 является минимальной production/dev/test версией. Конкретная major/minor версия OpenSpout выбирается
Composer-ом с учетом PHP версии окружения.

## Установка

```bash
composer require romanfedorskij/table-registry
```

Для локальной разработки:

```bash
composer install
composer check
```

### Скиллы Для AI-Агентов

Пакет поставляет два самостоятельных скилла:

- `table-registry-install` — установка, обновление и проверка Composer-пакета;
- `table-registry-use` — создание definition и интеграция query validation, SQL compilation, row projection и XLSX export.

Если в проекте используется Composer-плагин [`llm/skills`](https://packagist.org/packages/llm/skills), каталог
`skills/` объявлен в `composer.json` как источник и может быть синхронизирован в `.agents/skills/`:

```bash
composer require --dev llm/skills
composer skills:init --quick
composer skills:update romanfedorskij/table-registry
```

Composer запросит разрешение на запуск плагина при первой установке. Без плагина нужные директории можно скопировать
из `vendor/romanfedorskij/table-registry/skills/` в каталог скиллов используемого агента.

Более подробные сценарии использования вынесены в [docs/examples](docs/examples):

- описание table definition;
- сборка PostgreSQL SQL;
- projection rows;
- runtime constraints;
- PSR-container invoker;
- XLSX export.

## Быстрый Старт

```php
<?php

use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\ColumnSelectDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\SelectFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\TextFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Sort\RegistryDefaultSortDefinition;
use Wolfcharaa\TableRegistry\Sort\RegistrySortDefinition;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;

$definition = (new TableRegistryDefinition(
    registryKey: 'users',
    title: 'Пользователи',
    source: RegistrySourceDefinition::from('public.users u')
        ->withLeftJoin('public.user_profile p', 'p.user_id = u.id'),
    columns: [
        (new PrimaryKeyColumnRegistryDefinition('id'))
            ->withSelect(ColumnSelectDefinition::sqlExpression('id', 'u.id')),

        (new ColumnRegistryDefinition('fullName', 'ФИО'))
            ->withSelect(ColumnSelectDefinition::sqlExpression(
                'fullName',
                "concat_ws(' ', p.last_name, p.first_name)"
            ))
            ->withSearchable()
            ->withSort(RegistrySortDefinition::byExpression('fullName', 'p.last_name, p.first_name'))
            ->withFilter(TextFilterRegistryDefinition::contains(
                queryKey: 'filter_fullName',
                columnKey: 'fullName',
                title: 'ФИО'
            )),

        (new ColumnRegistryDefinition('status', 'Статус'))
            ->withSelect(ColumnSelectDefinition::sqlExpression('status', 'u.status'))
            ->withFilter(SelectFilterRegistryDefinition::static(
                queryKey: 'filter_status',
                columnKey: 'status',
                title: 'Статус',
                options: [
                    ['value' => 'active', 'label' => 'Активен'],
                    ['value' => 'blocked', 'label' => 'Заблокирован'],
                ]
            )),

        (new ColumnRegistryDefinition('actionToken', 'Action token'))
            ->withSelect(ColumnSelectDefinition::sqlExpression('actionToken', 'u.action_token'))
            ->withPayloadOnly(),

        (new ColumnRegistryDefinition('roleId', 'ID роли'))
            ->withSelect(ColumnSelectDefinition::sqlExpression('roleId', 'u.role_id'))
            ->withFilter(SelectFilterRegistryDefinition::static(
                queryKey: 'filter_roleId',
                columnKey: 'roleId',
                title: 'Роль',
                options: []
            ))
            ->withExportable(false),
    ]
))->withDefaultSort(RegistryDefaultSortDefinition::desc('id'));
```

## JSON-Контракт

`TableRegistryDefinition` реализует `JsonSerializable`.

```php
$payload = $definition->jsonSerialize();
```

Пример payload:

```json
{
  "schemaVersion": 2,
  "registryKey": "users",
  "title": "Пользователи",
  "primaryKey": "id",
  "columns": [
    {
      "key": "fullName",
      "title": "ФИО",
      "searchable": true,
      "sortable": true,
      "filterable": true,
      "filter": {
        "queryKey": "filter_fullName",
        "label": "ФИО",
        "columnKey": "fullName",
        "valueFormat": "string",
        "operators": ["contains"]
      }
    }
  ],
  "query": {
    "search": {
      "enabled": true,
      "queryKey": "search"
    },
    "pagination": {
      "type": "page",
      "pageKey": "page",
      "perPageKey": "per_page",
      "defaultPerPage": 25,
      "maxPerPage": 250
    },
    "defaultSort": {
      "column": "id",
      "direction": "desc"
    }
  }
}
```

В JSON не попадают backend-only детали: `source`, joins, SQL expressions, runtime constraints, hydration callbacks и
label resolvers.

Колонки, помеченные `withPayloadOnly()`, также не попадают в `columns` JSON definition. При этом они остаются частью
backend definition: SQL compiler выбирает их в rows-запросе, `RegistryRowsProjector` отдаёт их в `row.values`, а
frontend может использовать эти значения для row actions, detail modal или других feature-specific сценариев без
появления таких полей в настройках отображения таблицы.

Колонки, помеченные `withExportable(false)`, остаются в JSON definition, rows payload, сортировках и фильтрах, но не
попадают в набор колонок, доступных для XLSX export. Это удобно для технических id-полей, которые нужны frontend для
фильтра или перехода, но не должны попадать в файл выгрузки. `withPayloadOnly()` колонки также считаются неэкспортируемыми.

Output-only колонка описывает значение, которое приложение добавляет в raw row после SQL и до projection:

```php
(new ColumnRegistryDefinition('personLabel', 'ФИО'))
    ->withOutputOnly();
```

Такая колонка попадает в JSON definition и rows payload, но `PostgresRegistrySqlCompiler` не добавляет её в `SELECT`.
По умолчанию она не участвует в search/sort/filter/export. Если приложению нужно выгружать уже подготовленное
output-only значение, export можно включить явно через `withExportable(true)`.

## Source

`RegistrySourceDefinition` описывает SQL-фрагмент после `FROM` и optional joins.

```php
$source = RegistrySourceDefinition::from('public.orders o')
    ->withLeftJoin('public.users u', 'u.id = o.user_id')
    ->withConnection('default')
    ->withCountSource('public.orders o');
```

`withConnection()` хранит opaque logical scope источника данных. Библиотека не открывает connection и не проверяет
доступность scope; приложение само решает, как сопоставить строку `default`, `warehouse` или другое имя с реальным
PDO/DBAL connection.

`from()` может быть таблицей, view, materialized view или читаемым SQL-фрагментом:

```php
RegistrySourceDefinition::from('(SELECT * FROM public.users WHERE deleted_at IS NULL) u');
```

`withCountSource()` задаёт отдельный `FROM` только для `compileCount()`. Joins, filters, search expressions и runtime
constraints остаются теми же, поэтому count source должен сохранять alias и SQL-поля, на которые ссылается definition.
Это полезно, когда rows читаются из view или тяжелого source, а count можно безопасно считать по более простому source.
Если приложение исторически хранит в `countSource` opaque key для собственного snapshot/cache-счётчика, например
`fis_export_package:active`, compiler не использует такой key как SQL `FROM` и вернётся к обычному `from()`.

Не подставляйте пользовательский ввод в `source` и joins. Пользовательские значения должны проходить через filters,
query input и параметры SQL compiler-а.

## Columns

Минимальная колонка знает ключ и человекочитаемый заголовок:

```php
new ColumnRegistryDefinition('email', 'Email');
```

Если `withSelect()` не указан, compiler использует quoted identifier:

```sql
"email" AS "email"
```

Для сложного выражения используйте `ColumnSelectDefinition`:

```php
(new ColumnRegistryDefinition('fullName', 'ФИО'))
    ->withSelect(ColumnSelectDefinition::sqlExpression(
        'fullName',
        "concat_ws(' ', last_name, first_name, middle_name)"
    ));
```

Primary key обязателен:

```php
new PrimaryKeyColumnRegistryDefinition('id');
```

Служебная колонка только для payload/action:

```php
(new ColumnRegistryDefinition('actionToken', 'Action token'))
    ->withSelect(ColumnSelectDefinition::sqlExpression('actionToken', 'u.action_token'))
    ->withPayloadOnly();
```

Primary key нельзя сделать payload-only: он всегда остаётся частью публичного contract как `primaryKey`.

## Фильтры

Встроенные фильтры:

- `TextFilterRegistryDefinition::contains()` и `::exact()`;
- `IntegerFilterRegistryDefinition::exact()`;
- `BooleanFilterRegistryDefinition::exact()`;
- `NullabilityFilterRegistryDefinition`;
- `DateRangeFilterRegistryDefinition`;
- `TimeRangeFilterRegistryDefinition`;
- `SelectFilterRegistryDefinition::static()`;
- `SelectFilterRegistryDefinition::preload()`;
- `SelectFilterRegistryDefinition::async()`.

Select filter может отдавать frontend статичные options:

```php
SelectFilterRegistryDefinition::static(
    queryKey: 'filter_status',
    columnKey: 'status',
    title: 'Статус',
    options: [
        ['value' => 'active', 'label' => 'Активен'],
    ]
);
```

Для preload/async фильтров route задаётся явно:

```php
SelectFilterRegistryDefinition::preload(
    queryKey: 'filter_koap',
    columnKey: 'koapCode',
    title: 'КоАП',
    route: '/api/table/options/nsi/koap'
);
```

## Поиск И Сортировка

Колонка участвует в global search только если вызван `withSearchable()`:

```php
$column = $column->withSearchable();
```

Сортировка включается через `withSort()`:

```php
$column = $column->withSort(RegistrySortDefinition::byColumn('createdAt'));
```

Для SQL expression:

```php
$column = $column->withSort(
    RegistrySortDefinition::byExpression('fullName', 'last_name, first_name')
);
```

Default sort хранится на уровне таблицы:

```php
$definition = $definition->withDefaultSort(RegistryDefaultSortDefinition::desc('id'));
```

## SQL Compiler

`PostgresRegistrySqlCompiler` собирает SQL и params, но не выполняет запрос.

```php
use Wolfcharaa\TableRegistry\Sql\PostgresRegistrySqlCompiler;

$compiled = (new PostgresRegistrySqlCompiler())->compileRows($definition, [
    'search' => 'иван',
    'filter_status' => ['active', 'blocked'],
    'sort_col' => 'fullName',
    'sort_dir' => 'desc',
    'page' => 1,
    'per_page' => 25,
]);

$sql = $compiled->sql();
$params = $compiled->params();
```

Доступные методы:

- `compileRows()` - rows query с pagination;
- `compileCount()` - count query;
- `compileExportRows()` - rows query без pagination для export.

Выполнение SQL остаётся ответственностью приложения.

## Query Validation

`RegistryQueryValidator` проверяет raw query параметры относительно `TableRegistryDefinition`, но не меняет поведение
compiler-а сам по себе. Это позволяет приложению включить strict validation перед выполнением SQL и самостоятельно
решить, как превратить ошибки в HTTP response.

```php
use Wolfcharaa\TableRegistry\Query\RegistryQueryValidator;

$validation = (new RegistryQueryValidator())->validate($definition, [
    'sort_col' => 'fullName',
    'sort_dir' => 'desc',
    'filter_status' => ['active', 'blocked'],
    'page' => '1',
    'per_page' => '25',
]);

if (!$validation->isValid()) {
    $issues = $validation->jsonSerialize()['issues'];
}
```

Проверяются:

- неизвестные query keys;
- неизвестные или несортируемые sort columns;
- sort direction;
- page/per-page значения и max per-page;
- filter values после нормализации;
- date/time range format;
- отключенный search или search без searchable columns.

Можно использовать `assertValid()`, если приложению удобнее работать с exception:

```php
$validation->assertValid();
```

## Runtime Constraints

Runtime constraints позволяют добавить условия, зависящие от пользователя, tenant, подразделения или другого
контекста приложения.

```php
use Wolfcharaa\TableRegistry\Sql\RegistrySqlCondition;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlRuntimeContext;

$definition = $definition->withRuntimeConstraint(
    static function (RegistrySqlRuntimeContext $context): RegistrySqlCondition {
        $param = $context->param(42);

        return RegistrySqlCondition::raw('u.department_id = :' . $param);
    }
);
```

Для интеграции с DI используйте `PsrContainerRegistryResolverInvoker`. Он получает class-string resolver из
`Psr\Container\ContainerInterface`, а для callable и обычных invokable classes использует native fallback.

```php
use Wolfcharaa\TableRegistry\Runtime\PsrContainerRegistryResolverInvoker;

$projector = new RegistryRowsProjector(
    new PsrContainerRegistryResolverInvoker($container)
);
```

## Rows Payload

`RegistryRowsProjector` преобразует строки из БД в стабильный payload:

```php
use Wolfcharaa\TableRegistry\Row\RegistryRowsProjector;

$rows = (new RegistryRowsProjector())->project($definition, [
    ['id' => 1, 'status' => 'active'],
]);
```

Результат:

```json
[
  {
    "primaryKey": "1",
    "values": {
      "status": {
        "value": "active",
        "label": "active"
      }
    }
  }
]
```

`label` всегда строка. Если label resolver не задан, label строится из hydrated value. Для `DateTimeValue`
используется формат `d.m.Y H:i`.

Если приложению нужен стабильный wrapper для страницы таблицы, используйте `RegistryRowsResult`. Он не выполняет SQL,
а только сериализует уже спроецированные rows, total и нормализованный query input:

```php
use Wolfcharaa\TableRegistry\Row\RegistryRowsResult;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlQueryInput;

$query = RegistrySqlQueryInput::fromArray($definition, $_GET);
$projectedRows = (new RegistryRowsProjector())->project($definition, $sqlRows);

$payload = new RegistryRowsResult(
    definition: $definition,
    query: $query,
    total: $total,
    rows: $projectedRows
);
```

JSON contract:

```json
{
  "schemaVersion": 2,
  "registryKey": "users",
  "rows": [],
  "total": 123,
  "query": {
    "page": 1,
    "perPage": 25,
    "search": "",
    "sort": {
      "column": "",
      "direction": "asc"
    }
  }
}
```

## Value Objects И Labels

Колонка может нормализовать raw value через `PrimitiveValueObjectInterface`:

```php
use Wolfcharaa\TableRegistry\Value\DateTimeValue;

$column = (new ColumnRegistryDefinition('createdAt', 'Дата создания'))
    ->withRowTypeObject(DateTimeValue::class);
```

Label можно задать строкой, callback или class-string resolver-а:

```php
$column = $column->withLabel(
    static fn ($value, $row, $column): string => $value->getValue() === 'active' ? 'Активен' : 'Неактивен'
);
```

Если строка является существующим классом, класс должен реализовать `ColumnValueLabelResolverInterface`.

## Export

Сначала выберите запрошенные колонки. Resolver сам ограничит их export policy из `TableRegistryDefinition`:

```php
use Wolfcharaa\TableRegistry\Export\RegistryExportColumnResolver;

$columns = (new RegistryExportColumnResolver())->resolve(
    definition: $definition,
    requestedColumnKeys: ['fullName', 'status']
);
```

Если `requestedColumnKeys` пустой, resolver вернёт все колонки, для которых `exportable() === true`.

Для плоских строк из projected rows:

```php
use Wolfcharaa\TableRegistry\Row\RegistryProjectedRowsFlattener;

$flatRows = RegistryProjectedRowsFlattener::flattenLabels($projectedRows);
```

XLSX:

```php
use Wolfcharaa\TableRegistry\Export\RegistryXlsxExporter;

$result = (new RegistryXlsxExporter())->export(
    definition: $definition,
    columns: $columns,
    rows: $flatRows,
    fileNamePrefix: 'users'
);

$filePath = $result->filePath();
$fileName = $result->fileName();
```

## Разработка И Проверки

Единый локальный gate проверяет Composer metadata, весь Testo suite, PHPStan на максимальном уровне и каждый пример из
`docs/examples/`:

```bash
composer check
```

Отдельные проверки можно запускать командами:

```bash
composer test
composer test:unit
composer test:contract
composer test:characterization
composer analyse
composer examples
```

Testo разделён на три suite:

- `Unit` проверяет поведение отдельных компонентов;
- `Contract` сверяет [поддерживаемый public API](docs/public-api.md) и reflection signature snapshot;
- `Characterization` фиксирует совместимость наблюдаемых JSON, SQL, параметров и projected rows с линией `v0.5.x`.

Направление совместимости для следующей minor-линии описано в [BC matrix](docs/v0.6-bc-matrix.md). Документационные
примеры являются исполняемой частью quality gate и не должны оставлять временные артефакты.

Unit-тесты покрывают:

- JSON-контракт definition;
- валидацию primary key и дубликатов колонок;
- PostgreSQL SQL compiler;
- runtime constraint callback;
- projection rows в `{primaryKey, values}`;
- label resolver для строк;
- export column resolver;
- flatten projected rows;
- PostgreSQL identifier/literal value objects.
