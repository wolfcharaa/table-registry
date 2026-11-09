# Examples

[English](../en/examples/README.md)

В этой директории лежат небольшие самодостаточные примеры работы с библиотекой.

- `00-quick-start.php` - минимальный definition и первый наблюдаемый frontend-контракт.
- `01-table-definition.php` - описание таблицы, колонок, payload-only колонок, фильтров, поиска и default sort.
- `02-postgres-sql-compiler.php` - сборка PostgreSQL SQL для rows/count/export.
- `03-row-projection.php` - подготовка строк в `{primaryKey, values}` payload и label resolver.
- `04-runtime-constraint.php` - runtime SQL-ограничение через callback.
- `05-xlsx-export.php` - выбор колонок, flatten labels и XLSX export.
- `06-psr-container-invoker.php` - получение resolver-а из PSR container по class-string.
- `07-standalone-option-list.php` - самостоятельный `SelectDefinition`, runtime scope, SQL compilation и typed options.
- `08-attribute-table.php` - минимальные relation/aggregate Attributes и compiled frontend contract.
- `09-attribute-join.php` - две reusable relation models, structured `LEFT JOIN` и SQL compilation.
- `10-attribute-variants.php` - default/archive family и variant-only column.
- `11-attribute-external-rows.php` - projection внешних строк и ожидаемый отказ SQL compiler-а.

Примеры не выполняют SQL-запросы. Библиотека возвращает SQL и параметры, а выполнение остаётся ответственностью
приложения и его DB-адаптера.

Все примеры входят в исполняемый контракт библиотеки. Из source checkout их можно проверить отдельно командой:

```bash
composer examples
```

Каждый файл запускается в отдельном PHP-процессе. XLSX-пример дополнительно проверяет создание непустого файла, после
чего runner удаляет его из системного временного каталога.
