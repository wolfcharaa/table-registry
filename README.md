# TableRegistry

`TableRegistry` — framework-agnostic PHP-библиотека для описания табличных и самостоятельных read-моделей,
компиляции PostgreSQL-запросов, нормализации строк под frontend-контракт и выгрузки XLSX.

Библиотека полезна, когда backend должен явно зафиксировать колонки, фильтры, поиск, сортировку, пагинацию и форму
ответа, но выполнение SQL, HTTP, авторизация и бизнес-действия должны оставаться в приложении.

Документация ведётся на русском языке. Имена public API, команды и исполняемые примеры общие для всех потребителей.

## Что Даёт Библиотека

- `TableRegistryDefinition` для таблиц с обязательным primary key и сериализуемым frontend-контрактом;
- `SelectDefinition` для options, списков и небольших read-моделей без table metadata;
- строгую валидацию canonical query input;
- PostgreSQL-компиляцию rows/count/export SQL и параметров без выполнения запроса;
- projection raw rows в стабильные table и option payload;
- runtime constraints для actor-, tenant- и scope-зависимых условий;
- policy выбора экспортируемых колонок и запись XLSX через OpenSpout.

Библиотека не открывает соединение с БД, не читает HTTP request, не авторизует route, не определяет CRUD-команды и
не управляет layout или действиями frontend. Эти обязанности принадлежат host application.

## Требования И Установка

- PHP 8.2 или новее;
- `ext-json` и `ext-mbstring`;
- PostgreSQL для SQL, который компилирует библиотека;
- `psr/container` `^1.1 || ^2.0`;
- `openspout/openspout` `^4.28 || ^5.0` для XLSX.

Установите стабильную линию `v0.6.x` из Packagist:

```bash
composer require romanfedorskij/table-registry:^0.6
```

## Быстрый Старт

Создайте `quick-start.php` рядом с `vendor/`:

```php
<?php

declare(strict_types=1);

use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;

require __DIR__ . '/vendor/autoload.php';

$definition = new TableRegistryDefinition(
    registryKey: 'users',
    title: 'Users',
    source: RegistrySourceDefinition::from('public.users'),
    columns: [
        new PrimaryKeyColumnRegistryDefinition('id'),
        new ColumnRegistryDefinition('email', 'Email'),
    ]
);

$contract = $definition->jsonSerialize();

echo sprintf(
    "registry=%s primary=%s columns=%d\n",
    $contract['registryKey'],
    $contract['primaryKey'],
    count($contract['columns'])
);
```

Запустите:

```bash
php quick-start.php
```

Ожидаемый результат:

```text
registry=users primary=id columns=2
```

Этот результат подтверждает первый публичный контракт библиотеки без БД и web-фреймворка. Поддерживаемый исходник
примера находится в [`docs/examples/00-quick-start.php`](docs/examples/00-quick-start.php).

## Как Устроена Интеграция

```text
HTTP/CLI adapter → validate input → compile SQL + params → application executes SQL
                 → project raw rows → serialize response / export XLSX
```

`TableRegistryDefinition` нужен, когда frontend получает описание таблицы и rows payload. `SelectDefinition` подходит
для options и других read-моделей, которым не нужны primary key и table metadata. В обоих вариантах приложение
владеет transport parsing, DB adapter, authorization и response envelope.

## Документация

Начните с [карты документации](docs/README.md) или сразу выберите задачу:

- [построить table read-model от definition до response](docs/table-read-model.md);
- [построить standalone select или typed option list](docs/select-and-options.md);
- [запустить исполняемые примеры](docs/examples/README.md);
- [проверить поддерживаемые public symbols](docs/public-api.md);
- [обновиться с `v0.5.x` на `v0.6.0`](docs/migration-v0.5-to-v0.6.md);
- [понять стратегию совместимости `v0.6.x`](docs/v0.6-bc-matrix.md).

## Скиллы Для AI-Агентов

Пакет поставляет два скилла:

- `table-registry-install` — установка, обновление и проверка Composer-пакета;
- `table-registry-use` — создание definition и интеграция validation, compilation, projection и export.

С Composer-плагином [`llm/skills`](https://packagist.org/packages/llm/skills) их можно синхронизировать в проект:

```bash
composer require --dev llm/skills
composer skills:init --quick
composer skills:update romanfedorskij/table-registry
```

Composer запросит отдельное разрешение на запуск плагина. Без плагина скопируйте нужный каталог из
`vendor/romanfedorskij/table-registry/skills/` в configured skills root агента; не редактируйте копию внутри
`vendor/`.

## Совместимость

Поддерживаемая поверхность `v0.6.x` перечислена в [`docs/public-api.md`](docs/public-api.md) и проверяется signature
snapshot-ом. Наличие класса под `src/` само по себе не означает compatibility promise; consumer code должен зависеть
только от символов с `@api` из allowlist.

## Разработка

```bash
composer install
composer check
```

`composer check` валидирует Composer metadata, запускает unit/contract/characterization suites, PHPStan и все
примеры из `docs/examples/`.

## Лицензия

[MIT](LICENSE)
