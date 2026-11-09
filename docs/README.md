# Документация TableRegistry

[English](en/README.md)

Документация ветки `main` относится к готовящейся линии `v0.7`. Русский — primary locale; PHP symbols, команды и
примеры общие для обеих локалей.

## Рекомендуемый Порядок

1. Выполните [быстрый старт](../README.md#быстрый-старт), чтобы получить первый frontend-контракт без БД.
2. Для новой static table definition используйте [Attribute authoring](attributes.md).
3. Для полного runtime pipeline пройдите [table read-model](table-read-model.md).
4. Для options или небольшой read-модели используйте [standalone select](select-and-options.md).
5. Сверяйте доступные symbols с [public API](public-api.md), а наблюдаемое поведение — с
   [исполняемыми примерами](examples/README.md).

## По Задаче

| Задача | Документ |
| --- | --- |
| Оценить назначение, требования и границы | [README](../README.md) |
| Описать relation/JOIN aggregate через Attributes | [Attribute authoring](attributes.md) |
| Проверить exact Attribute semantics и ошибки | [Attribute reference](attribute-reference.md) |
| Собрать table endpoint | [Table read-model](table-read-model.md) |
| Собрать options/list endpoint | [Select и option lists](select-and-options.md) |
| Найти полный запускаемый сценарий | [Examples](examples/README.md) |
| Проверить compatibility promise symbol-а | [Public API](public-api.md) |
| Обновить consumer с `v0.5.x` | [Migration `v0.5.x` → `v0.6.0`](migration-v0.5-to-v0.6.md) |
| Перейти на Attribute frontend | [Migration `v0.6.x` → `v0.7.0`](migration-v0.6-to-v0.7.md) |
| Понять статус legacy и generic API | [`v0.6.0` compatibility matrix](v0.6-bc-matrix.md) |

## Граница С Host Application

TableRegistry описывает read-model, валидирует canonical input, компилирует SQL/params и проектирует уже полученные
строки. Приложение разбирает transport input, выбирает и открывает соединение, выполняет SQL, задаёт authorization,
управляет transaction/response lifecycle и удаляет временный XLSX-файл после отправки.

Application startup, migrations, database provisioning, deployment, backup и production credentials не входят в
документацию библиотеки.
