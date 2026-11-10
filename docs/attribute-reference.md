# Attribute reference

[English](en/attribute-reference.md)

Reference относится к линии `v0.7`. Для первого сценария начните с
[`attributes.md`](attributes.md), а public signatures сверяйте с [`public-api.md`](public-api.md).

## Роли и источники

| Роль | Обязательные Attributes | Назначение |
| --- | --- | --- |
| Relation model | ровно один `Relation`, `RawRelation` или `RelationSourceProvider` | Reusable physical/derived source и `Field` metadata |
| Aggregate | `Table` либо семейство `Variant`; ровно один `From` или `ExternalRows` | Конечный table/read-model contract |
| Mapped source | `MappedFields` на parent/trait | Явный импорт reusable fields без source |

Роли взаимоисключающие. Aggregate терминален и не может быть участником другого aggregate. Compiler читает только
явно переданные class strings и никогда не создаёт экземпляры annotated classes.

## Class-level Attributes

| Attribute | Repeatable | Семантика |
| --- | ---: | --- |
| `Table(key, title)` | нет | Identity одиночного aggregate; несовместим с `Variant` |
| `Variant(name, key, title, isDefault)` | да | Именованное конечное семейство; ровно один default |
| `From(model, alias)` | нет | Root relation participant |
| `ExternalRows(key)` | нет | Non-SQL provider rows; запрещает `From`, `Join`, count и SQL constraints |
| `Join(model, alias, type, position, on)` | да | Ordered `Inner`/`Left` join; `FieldMatch[]` либо `RawJoinCondition` |
| `CountSource(source, variant)` | да | Optional count source; variant-scoped значение заменяет common |
| `Search` | нет | Table search query key и enabled flag |
| `Pagination(..., variant)` | да | Common/variant pagination policy |
| `DefaultSort(column, direction, variant)` | да | Common/variant default sort; column обязана существовать |
| `SqlConstraint(sql, params, variant)` | да | Trusted fixed predicate с literal params |
| `RuntimeConstraint(resolver, variant)` | да | Class-string `RegistrySqlRuntimeConstraintInterface` |
| `Relation(identifier, connection)` | нет | Qualified relation/view identifier |
| `RawRelation(sql, connection)` | нет | Trusted reusable derived source |
| `RelationSourceProvider(provider)` | нет | Compile-time `RelationSourceProviderInterface` |
| `MappedFields` | нет | Разрешает явный импорт fields из parent/trait |
| `OverrideField` / `ExcludeField` | да | Изменяет или удаляет только imported field |

`Join` positions и aliases уникальны. Все SQL participants должны иметь одну connection. Structured join проверяет
только metadata references; существование relation/column остаётся ответственностью PostgreSQL.

## Property-level Attributes

| Группа | Attributes | Правило |
| --- | --- | --- |
| Relation mapping | `Field` | Physical column, row value-object type и nullability |
| Public column | `Column`, `PrimaryKey` | Unique key/positive position; ровно один visible primary key |
| SQL input | `Project`, `SqlExpression`, `SqlExpressionProvider` | Ровно один input для обычной SQL-column |
| External input | `ExternalField` | Provider row key для external aggregate |
| Resolver-only input | `Internal` | Не public/payload/query/export capability |
| Raw value | `RawType` | Обязателен для hydrated computed/external inputs |
| Presentation | `Label`, `LabelFrom`, `LabelWith`, `HydrateWith` | Одна label strategy; complex resolvers имеют explicit inputs |
| Query | `Searchable`, `Sort`, filter Attributes | Query capabilities принадлежат aggregate column |
| Membership | `OnlyInVariant`, `ExcludeFromVariant` | Взаимоисключающие ссылки только на существующие variants |

Filter Attributes: `TextFilter`, `IntegerFilter`, `BooleanFilter`, `NullabilityFilter`, `DateRangeFilter`,
`TimeRangeFilter`, `StaticSelectFilter`, `PreloadSelectFilter`, `AsyncSelectFilter`. На property допустим только один
filter. `ColumnMode::Visible` — обычная колонка, `Payload` скрыт из definition JSON, `Output` не создаёт SQL select.

PHP visibility, constructor, methods, initialized/promoted/readonly properties не влияют на compilation. Static
annotated properties запрещены.

## Важные Defaults

- `Column::key`, `Project::field`, `Field::column` и `ExternalField::key` по умолчанию равны имени property.
- `Field::rowType` и nullability выводятся из PHP property type; explicit metadata нужна, когда тип вывести нельзя.
- Filter query key по умолчанию равен column key; range keys — `<key>_from` и `<key>_to`.
- `Search` использует `search`; `Pagination` — `page`/`per_page`, `25` по умолчанию и максимум `250`.
- `Sort` без expression сортирует по select expression колонки.
- `Column::exportable = null` сохраняет default policy режима; payload/output не экспортируются по умолчанию.
- `LabelFrom` fallback-ит к исходному value, если связанное значение равно `null`; это отключается
  `fallbackToValue: false`.

Exact constructor signatures и enum cases зафиксированы в [`public-api.md`](public-api.md) и signature snapshot.

## Providers и resolvers

`AttributeTableRegistryCompiler` по умолчанию использует `NativeCompileTimeProviderResolver`, который создаёт только
zero-argument providers. Для DI передайте `PsrContainerCompileTimeProviderResolver`; он читает PSR container и
fallback-ит к native resolver.

Provider object и его результат вычисляются один раз на `compile()`, `compileAll()` или `compileVariants()`, затем
замораживаются в definition. Provider не получает request/query/user context. Ошибка при вычислении provider output
мемоизируется в этой операции и возвращается как location-bound `AttributeCompilationIssue`.

`LabelWith` и `HydrateWith` получают scoped `RegistryRowData`, содержащий только перечисленные aggregate properties.
`LabelFrom` — короткий путь для одного значения. DI-managed runtime class strings разрешаются уже при SQL/projection
через `RegistryResolverInvokerInterface`, а не compile-time provider resolver.

Рекомендуемый host lifecycle — compile once при composition/bootstrap и reuse immutable definitions. Compiler не
хранит global cache; «один раз» для providers означает одну compilation operation. Полный composition и failure
contract extension ports находится в [`extensions.md`](extensions.md).

## Trusted SQL boundary

Developer-controlled SQL допускается только в явно SQL-oriented metadata: `RawRelation`, `RawJoinCondition`,
`SqlExpression`, provider results, `Sort` expression, `SqlConstraint` и object API. Request values туда не
интерполируются. `SqlConstraint::params` перепривязываются через runtime parameter binder; dynamic values используют
filters/query input или runtime constraints.

Compiler не выполняет database/schema/network validation. Он проверяет role, Attribute structure, aliases, metadata
fields, types, positions, capabilities, connections и каждый variant, после чего existing runtime validators проверяют
готовый definition.

## Compilation API

```php
$definition = $compiler->compile(Aggregate::class);             // sole/default
$archive = $compiler->compile(Aggregate::class, 'archive');     // explicit variant
$catalog = $compiler->compileAll([A::class, B::class]);         // defaults by registry key
$family = $compiler->compileVariants(Aggregate::class);         // all variants by name
```

Операция либо возвращает полный immutable result, либо один `AttributeCompilationException` с ordered issues. Partial
collection не возвращается.

## Stable issue codes

Список зафиксирован contract fixture `tests/Contract/fixtures/attribute-compilation-issue-codes.json`:

`alias.invalid`, `attribute.invalid`, `capability.invalid`, `catalog.duplicate_key`, `definition.invalid`,
`field.invalid`, `inheritance.invalid`, `join.invalid`, `position.invalid`, `projection.invalid`,
`projection.provider`, `resolver.invalid`, `role.conflict`, `source.invalid`, `source.provider`, `type.invalid`,
`variant.invalid`.

Issue всегда содержит code, message и consumer class; variant/property/Attribute/provider заполняются, когда ошибка
имеет соответствующую точную location.
