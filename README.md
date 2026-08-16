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
- runtime constraints через callback или invokable class;
- value object нормализация значений строк;
- label/hydration resolvers для человекочитаемого payload;
- flatten projected rows для export/print;
- XLSX export через OpenSpout.

Библиотека не управляет действиями пользователя, кнопками, layout таблицы, проверками доступа на уровне маршрута,
CRUD-командами и HTTP-роутингом. Эти решения должны оставаться в приложении, которое использует библиотеку.

## Требования

- PHP 8.1 или выше;
- `ext-json`;
- `ext-mbstring`;
- `psr/container`;
- `openspout/openspout` для XLSX export;
- `phpunit/phpunit` только для разработки и тестов.

## Установка

```bash
composer require romanfedorskij/table-registry
```

Для локальной разработки:

```bash
composer install
composer test
```

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

use TableRegistry\Column\ColumnRegistryDefinition;
use TableRegistry\Column\ColumnSelectDefinition;
use TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use TableRegistry\Definition\TableRegistryDefinition;
use TableRegistry\Filter\SelectFilterRegistryDefinition;
use TableRegistry\Filter\TextFilterRegistryDefinition;
use TableRegistry\Sort\RegistryDefaultSortDefinition;
use TableRegistry\Sort\RegistrySortDefinition;
use TableRegistry\Source\RegistrySourceDefinition;

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

## Source

`RegistrySourceDefinition` описывает SQL-фрагмент после `FROM` и optional joins.

```php
$source = RegistrySourceDefinition::from('public.orders o')
    ->withLeftJoin('public.users u', 'u.id = o.user_id')
    ->withConnection('default')
    ->withCountSource('orders');
```

`from()` может быть таблицей, view, materialized view или читаемым SQL-фрагментом:

```php
RegistrySourceDefinition::from('(SELECT * FROM public.users WHERE deleted_at IS NULL) u');
```

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
use TableRegistry\Sql\PostgresRegistrySqlCompiler;

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

## Runtime Constraints

Runtime constraints позволяют добавить условия, зависящие от пользователя, tenant, подразделения или другого
контекста приложения.

```php
use TableRegistry\Sql\RegistrySqlCondition;
use TableRegistry\Sql\RegistrySqlRuntimeContext;

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
use TableRegistry\Runtime\PsrContainerRegistryResolverInvoker;

$projector = new RegistryRowsProjector(
    new PsrContainerRegistryResolverInvoker($container)
);
```

## Rows Payload

`RegistryRowsProjector` преобразует строки из БД в стабильный payload:

```php
use TableRegistry\Row\RegistryRowsProjector;

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

## Value Objects И Labels

Колонка может нормализовать raw value через `PrimitiveValueObjectInterface`:

```php
use TableRegistry\Value\DateTimeValue;

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

Сначала выберите разрешённые и запрошенные колонки:

```php
use TableRegistry\Export\RegistryExportColumnResolver;

$columns = (new RegistryExportColumnResolver())->resolve(
    definition: $definition,
    allowedColumnKeys: ['id', 'fullName', 'status'],
    requestedColumnKeys: ['fullName', 'status']
);
```

Для плоских строк из projected rows:

```php
use TableRegistry\Row\RegistryProjectedRowsFlattener;

$flatRows = RegistryProjectedRowsFlattener::flattenLabels($projectedRows);
```

XLSX:

```php
use TableRegistry\Export\RegistryXlsxExporter;

$result = (new RegistryXlsxExporter())->export(
    definition: $definition,
    columns: $columns,
    rows: $flatRows,
    fileNamePrefix: 'users'
);

$filePath = $result->filePath();
$fileName = $result->fileName();
```

## Тесты

```bash
composer test
```

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
