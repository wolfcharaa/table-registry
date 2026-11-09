# Migration `v0.6.x` → `v0.7.0`

[English](en/migration-v0.6-to-v0.7.md)

`v0.7.0` добавляет Attribute authoring frontend и сохраняет object-authored definitions. Обновление dependency не
требует переписывать существующие таблицы: оба пути выпускают один `TableRegistryDefinition` и могут сосуществовать.

## Обновление пакета

Перед final tag используйте точный release candidate; после project pilot перейдите на minor line:

```bash
composer require romanfedorskij/table-registry:"0.7.0-rc.1" --with-all-dependencies
composer show romanfedorskij/table-registry
composer check-platform-reqs
```

После публикации final:

```bash
composer require romanfedorskij/table-registry:"^0.7.0" --with-all-dependencies
```

Минимальная версия PHP остаётся `8.2`. Не используйте `--ignore-platform-reqs`. Если проект синхронизирует bundled
skills, обновите их после Composer update.

## Что добавлено

- Attributes для relation models, aggregate projection, joins, filters, resolvers, constraints и finite variants;
- `AttributeTableRegistryCompiler` и explicit `compile`/`compileAll`/`compileVariants` registration;
- compile-time provider ports с native и PSR-container resolution;
- typed external-row source и internal resolver-only row inputs;
- ordered `AttributeCompilationIssue` diagnostics со stable codes.

Object constructors/fluent methods, legacy JSON/SQL/projection behavior и standalone select API не удалены и не
deprecated.

## Пилот одной таблицы

1. Оставьте текущую definition factory как parity oracle.
2. Выделите reusable relation class с `Relation`/`Field` metadata.
3. Создайте aggregate с `Table`, `From`/`Join` и explicit `Column` projection.
4. Скомпилируйте новый вариант рядом со старым.
5. Сравните serialized definition, rows/count/export SQL и params, projection и export columns.
6. Только после parity переключите registration; старую factory удаляйте отдельным reversible commit.

Минимальный Attribute path находится в [`examples/08-attribute-table.php`](examples/08-attribute-table.php), JOIN —
в [`examples/09-attribute-join.php`](examples/09-attribute-join.php).

## Соответствие object API

| До `v0.7` | Attribute path |
| --- | --- |
| `RegistrySourceDefinition::from()` | relation `Relation` + aggregate `From` |
| `withInnerJoin()` / `withLeftJoin()` | `Join` + `FieldMatch` или `RawJoinCondition` |
| `new ColumnRegistryDefinition()` | aggregate property `Column` + input Attribute |
| `ColumnSelectDefinition::sqlExpression()` | `SqlExpression` / `SqlExpressionProvider` |
| auxiliary select для resolver-а | `Internal` property |
| fixed constraint callback | `SqlConstraint` |
| DI class-string constraint | `RuntimeConstraint` |
| отдельные active/archive factories | repeatable `Variant` family |

Request-dependent actor/tenant restrictions не переносятся в compile-time Attributes. Добавляйте их после compilation
через прежний immutable `withRuntimeConstraint()`.

## Сценарии, которые можно оставить на object API

- definition shape действительно меняется от runtime request/application state;
- consumer строит columns/options из динамического каталога;
- миграция не даёт JSON/SQL/projection parity;
- существующий builder является осознанной domain convention, а не повторением static metadata.

Attribute и object definitions можно регистрировать рядом через `compileAll()` и обычные application factories.

## Rollback

Attribute declarations не меняют DB schema. Для rollback верните application registration к прежней factory и
dependency constraint к последней проверенной `v0.6.x`:

```bash
composer require romanfedorskij/table-registry:"^0.6.0" --with-all-dependencies
```

Откатывайте вместе consumer code, импортирующий новые `Attribute\*`, `Compiler\*`, external rows или row inputs. Не
оставляйте такие imports при downgrade.
