# Документация TableRegistry

Документация относится к стабильной линии `v0.6.x` и ведётся на русском языке. PHP symbols, команды и примеры не
локализуются и служат общими исполняемыми артефактами.

## Рекомендуемый Порядок

1. Выполните [быстрый старт](../README.md#быстрый-старт), чтобы получить первый frontend-контракт без БД.
2. Для таблицы пройдите [table read-model](table-read-model.md) от definition до rows response.
3. Для options или небольшой read-модели используйте [standalone select](select-and-options.md).
4. Сверяйте доступные symbols с [public API](public-api.md), а наблюдаемое поведение — с
   [исполняемыми примерами](examples/README.md).

## По Задаче

| Задача | Документ |
| --- | --- |
| Оценить назначение, требования и границы | [README](../README.md) |
| Собрать table endpoint | [Table read-model](table-read-model.md) |
| Собрать options/list endpoint | [Select и option lists](select-and-options.md) |
| Найти полный запускаемый сценарий | [Examples](examples/README.md) |
| Проверить compatibility promise symbol-а | [Public API](public-api.md) |
| Обновить consumer с `v0.5.x` | [Migration `v0.5.x` → `v0.6.0`](migration-v0.5-to-v0.6.md) |
| Понять статус legacy и generic API | [`v0.6.0` compatibility matrix](v0.6-bc-matrix.md) |

## Граница С Host Application

TableRegistry описывает read-model, валидирует canonical input, компилирует SQL/params и проектирует уже полученные
строки. Приложение разбирает transport input, выбирает и открывает соединение, выполняет SQL, задаёт authorization,
управляет transaction/response lifecycle и удаляет временный XLSX-файл после отправки.

Application startup, migrations, database provisioning, deployment, backup и production credentials не входят в
документацию библиотеки.
