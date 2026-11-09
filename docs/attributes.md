# Attribute-описание таблиц

[English](en/attributes.md)

Начиная с линии `v0.7`, статические table read-model рекомендуется описывать PHP Attributes. Компилятор читает
обычные классы, не создаёт их экземпляры и выпускает тот же `TableRegistryDefinition`, что и object-authored API.
SQL compilation, projection, validation и XLSX export после этого работают без отдельной Attribute-ветки.

Полный минимальный исходник: [`examples/08-attribute-table.php`](examples/08-attribute-table.php).
Фрагменты ниже показывают metadata shape и предполагают imports/autoload из этого полного примера.

## Два типа классов

Relation model описывает только источник и поля данных:

```php
#[Relation('public.users')]
final class UserReadModel
{
    #[Field('user_id')]
    protected int $id;

    #[Field('email_address')]
    protected string $email;
}
```

Aggregate отдельно задаёт публичную таблицу и явно выбирает её projection:

```php
#[Table('users', 'Пользователи')]
#[From(UserReadModel::class, 'user')]
final class UsersTable
{
    #[Column('ID', 10)]
    #[Project('user')]
    #[PrimaryKey]
    protected int $id;

    #[Column('Email', 20)]
    #[Project('user')]
    protected string $email;
}

$definition = (new AttributeTableRegistryCompiler())->compile(UsersTable::class);
```

Relation model можно переиспользовать под разными aliases и в разных aggregates. Добавление `Field` само по себе
никогда не расширяет frontend-контракт: наружу попадают только properties с `Column` в конкретном aggregate.

## JOIN нескольких моделей

Aggregate владеет topology. Обычный `INNER`/`LEFT JOIN` описывается `Join` и одним или несколькими `FieldMatch`;
сложные PostgreSQL conditions используют явно trusted `RawJoinCondition`. Компилятор проверяет только объявленные
aliases и fields в памяти — он не читает schema БД и не открывает connection.

Запускаемый пример: [`examples/09-attribute-join.php`](examples/09-attribute-join.php).

## Computed, internal и external values

- `SqlExpression` задаёт простой trusted SQL expression; `SqlExpressionProvider` — DI/configuration-aware compile-time
  вариант.
- `Internal` выбирает resolver-only значение, которого нет в JSON, projected values, query capabilities и export.
- `LabelFrom`, `LabelWith` и `HydrateWith` получают только явно перечисленные row inputs.
- Для hydrated computed/external значения задайте `RawType`; concrete return type hydrator-а обязан совпадать с типом
  aggregate property. `unchecked: true` разрешает только отсутствующий/`mixed` return type.
- `ColumnMode::Output` читает уже подготовленное приложением значение и не добавляет SQL select.

## Варианты

Повторяемый `Variant` описывает конечное семейство, например active/archive. Ровно один вариант имеет
`isDefault: true`. Common properties участвуют во всех вариантах; `OnlyInVariant` и `ExcludeFromVariant` меняют
membership. Variant-scoped `Pagination`, `DefaultSort`, `CountSource`, `SqlConstraint` и `RuntimeConstraint` заменяют
common-настройку своего вида.

```php
$active = $compiler->compile(OrdersTable::class);
$archive = $compiler->compile(OrdersTable::class, 'archive');
$family = $compiler->compileVariants(OrdersTable::class);
```

Каждая операция валидирует всё семейство. `compileAll()` возвращает только default definition каждого aggregate и
отклоняет повторяющиеся registry keys. Запускаемый пример:
[`examples/10-attribute-variants.php`](examples/10-attribute-variants.php).

## Внешние строки

`ExternalRows` предназначен для строк, которые получает приложение не через PostgreSQL compiler. `ExternalField`
связывает public/internal property с ключом provider row. Projection остаётся общей, а попытка SQL compilation
заканчивается `UnsupportedRegistrySourceException`.

Запускаемый пример: [`examples/11-attribute-external-rows.php`](examples/11-attribute-external-rows.php).

## Граница приложения

Attribute compiler получает только explicit class strings и stable compile-time providers. Он не выполняет
filesystem discovery, SQL, network I/O, request parsing или authorization. Request-dependent tenant/actor scope
добавляйте после compilation через immutable `withRuntimeConstraint()`; application по-прежнему выбирает DB adapter,
выполняет SQL с params и управляет response lifecycle.

Полная матрица Attributes, provider lifecycle и коды ошибок находятся в
[`attribute-reference.md`](attribute-reference.md). Object API остаётся поддерживаемым для dynamic definitions и
пошаговой миграции.
