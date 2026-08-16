# Examples

В этой директории лежат небольшие самодостаточные примеры работы с библиотекой.

- `01-table-definition.php` - описание таблицы, колонок, payload-only колонок, фильтров, поиска и default sort.
- `02-postgres-sql-compiler.php` - сборка PostgreSQL SQL для rows/count/export.
- `03-row-projection.php` - подготовка строк в `{primaryKey, values}` payload и label resolver.
- `04-runtime-constraint.php` - runtime SQL-ограничение через callback.
- `05-xlsx-export.php` - выбор колонок, flatten labels и XLSX export.
- `06-psr-container-invoker.php` - получение resolver-а из PSR container по class-string.

Примеры не выполняют SQL-запросы. Библиотека возвращает SQL и параметры, а выполнение остаётся ответственностью
приложения и его DB-адаптера.
