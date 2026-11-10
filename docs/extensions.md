# Расширение TableRegistry

[English](en/extensions.md)

Этот документ описывает поддерживаемые application extension points линии `v0.7`: зачем они нужны, где живёт
consumer-код, как он подключается, когда вызывается и как проявляются ошибки. Для обычной интеграции сначала
используйте встроенные Attributes и definitions; interface нужен, только если проект действительно меняет поведение.

Точные signatures и compatibility promise находятся в [`public-api.md`](public-api.md).

## Карта Точек Расширения

| Задача | Контракт | Подключение | Момент вызова |
| --- | --- | --- | --- |
| Источник relation из конфигурации | `RelationSourceProviderInterface` | `RelationSourceProvider` + compile-time resolver | Attribute compilation |
| SQL expression из конфигурации | `SqlExpressionProviderInterface` | `SqlExpressionProvider` + compile-time resolver | Attribute compilation |
| Альтернативный источник relation metadata | `RelationMetadataProviderInterface` | constructor `AttributeTableRegistryCompiler` | Attribute compilation |
| Framework/DI resolution compile-time providers | `CompileTimeProviderResolverInterface` | constructor `AttributeTableRegistryCompiler` | Attribute compilation |
| Hydration или label значения | `ColumnValueHydratorInterface`, `ColumnValueLabelResolverInterface` | `HydrateWith`/`LabelWith` или object API + registry invoker | Row projection |
| Actor/tenant predicate таблицы | `RegistrySqlRuntimeConstraintInterface` | `RuntimeConstraint` или `withRuntimeConstraint()` | Rows/count/export compilation |
| Actor/tenant predicate standalone select | `SelectRuntimeConstraintInterface` | `SelectDefinition::withRuntimeConstraint()` | Select compilation |
| Framework/DI resolution runtime resolvers | `RegistryResolverInvokerInterface`, `ResolverInvokerInterface` | constructors SQL compiler/projector | SQL compilation/projection |
| Project-specific filter metadata | `RegistryFilterDefinitionInterface`, `RegistryFilterExpressionInterface` | object-authored column `withFilter()` | Validation и registry SQL compilation |
| Project-specific search metadata | `RegistrySearchDefinitionInterface` | `TableRegistryDefinition::withSearch()` | Serialization, validation и registry SQL compilation |
| Другой SQL backend для standalone select | `SelectCompilerInterface` | application DI/composition | По явному вызову приложения |

Код реализаций обычно размещают в application-модуле рядом с read-model либо в его infrastructure adapter. Пакет не
сканирует filesystem и не регистрирует реализации автоматически.

## Compile-Time Providers

`RelationSourceProviderInterface` возвращает полностью resolved `RelationSourceMetadata`, а
`SqlExpressionProviderInterface` — trusted SQL string. Они подходят для stable configuration, например schema или
имени view, но не для request, actor или tenant, меняющегося между запросами.

Для zero-argument классов достаточно встроенного `NativeCompileTimeProviderResolver`. Если provider имеет
constructor dependencies, передайте `PsrContainerCompileTimeProviderResolver` в compiler:

```php
$providerResolver = new PsrContainerCompileTimeProviderResolver($container);
$compiler = new AttributeTableRegistryCompiler(providers: $providerResolver);
$definition = $compiler->compile(UsersTable::class);
```

Container lookup выполняется по class-string. Если entry отсутствует, PSR resolver передаёт управление native
fallback. Entry обязан быть object, source provider — реализовать `RelationSourceProviderInterface` и вернуть
`RelationSourceMetadata::relation()` либо `::raw()`, expression provider — реализовать
`SqlExpressionProviderInterface` и вернуть непустой SQL expression.

В пределах одной операции `compile()`, `compileAll()` или `compileVariants()` каждый provider object и его результат
вычисляются один раз. Результат замораживается в immutable definition; глобального cache между операциями нет. Поэтому
скомпилируйте catalog один раз в application composition/bootstrap, сохраните definitions в shared application
registry и переиспользуйте их на запросах.

Исключение provider-а превращается в `AttributeCompilationException`: `source.provider` для source и
`projection.provider` для expression. Issue содержит consumer, Attribute и provider class; повторная ссылка на тот же
provider в этой операции получает тот же memoized результат или ошибку.

Полный запускаемый пример с PSR container находится в
[`examples/12-attribute-providers.php`](examples/12-attribute-providers.php).

## Собственный Relation Metadata Provider

`RelationMetadataProviderInterface` нужен, когда relation facts уже хранятся в project metadata и дублировать их
Attributes нельзя. Метод `metadata(class-string $model)` возвращает `RelationMetadata` либо `null`, если модель не
поддерживается. Aggregate по-прежнему ссылается на model class через `From`/`Join`.

```php
final class ProjectRelationMetadataProvider implements RelationMetadataProviderInterface
{
    public function metadata(string $model): ?RelationMetadata
    {
        if ($model !== LegacyUser::class) {
            return null;
        }

        return new RelationMetadata(
            RelationSourceMetadata::relation('public.legacy_user'),
            [
                new RelationFieldMetadata('id', 'user_id', IntegerValue::class, false),
                new RelationFieldMetadata('email', 'email_address', StringValue::class, false),
            ]
        );
    }
}

$compiler = new AttributeTableRegistryCompiler(
    relations: new ProjectRelationMetadataProvider()
);
```

Metadata должна содержать минимум одно поле с уникальным logical name, valid physical identifier и row type,
реализующим `PrimitiveValueObjectInterface`. Provider не должен возвращать request-dependent metadata: compiler
вызывает его во время сборки definition. Если проект должен одновременно читать custom metadata и обычные relation
Attributes, composite/fallback принадлежит приложению; один переданный provider полностью заменяет
`AttributeRelationMetadataProvider`.

## Runtime Constraints

Runtime constraint получает только typed context и возвращает condition либо `null`. Dependencies входят через
constructor/DI, dynamic values — только через `context->param()`.

```php
final class AllowedDepartmentConstraint implements RegistrySqlRuntimeConstraintInterface
{
    public function __construct(private AccessPolicy $policy)
    {
    }

    public function __invoke(RegistrySqlRuntimeContext $context): ?RegistrySqlCondition
    {
        $ids = $this->policy->departmentIds();
        if ($ids === []) {
            return RegistrySqlCondition::impossible();
        }

        $params = array_map(
            static fn (int $id): string => ':' . $context->param($id),
            $ids
        );

        return RegistrySqlCondition::raw('department_id IN (' . implode(', ', $params) . ')');
    }
}
```

Class-string подключается через `RuntimeConstraint` Attribute или `withRuntimeConstraint()`. Передайте один
`PsrContainerRegistryResolverInvoker` в `PostgresRegistrySqlCompiler` и `RegistryRowsProjector`, если тот же container
разрешает constraints, hydrators и labels. Constraint таблицы применяется одинаково к rows, count и export.

Для standalone select используется отдельная пара `SelectRuntimeConstraintInterface` /
`SelectRuntimeContext` и `Select\Runtime\PsrContainerResolverInvoker`. В этом API `param()` возвращает полный
placeholder с `:`, а legacy registry context — имя без `:`. Возвращаемое значение другого типа приводит к
`SelectCompilationException` или registry compilation error. Route authorization остаётся отдельной проверкой.

Запускаемые сценарии: [`examples/04-runtime-constraint.php`](examples/04-runtime-constraint.php) и
[`examples/07-standalone-option-list.php`](examples/07-standalone-option-list.php).

## Hydrators И Labels

`ColumnValueHydratorInterface` преобразует raw `PrimitiveValueObjectInterface` в domain/view value.
`ColumnValueLabelResolverInterface` возвращает строковый label. Оба получают scoped `RegistryRowData` и текущую
`ColumnRegistryDefinition`; Attribute frontend передаёт в row только inputs, явно перечисленные в `HydrateWith` или
`LabelWith`.

Разместите resolver рядом с read-model, зарегистрируйте class-string в container и используйте
`PsrContainerRegistryResolverInvoker` при создании projector-а. Native invoker подходит только для callable или
zero-argument invokable class. Container entry обязан быть callable; несовпадение interface/return type выявляется
при Attribute compilation либо projection.

```php
$invoker = new PsrContainerRegistryResolverInvoker($container);
$projector = new RegistryRowsProjector($invoker);
$rows = $projector->project($definition, $databaseRows);
```

Полный пример label resolver-а и container composition:
[`examples/06-psr-container-invoker.php`](examples/06-psr-container-invoker.php).

## Custom Filters И Search Metadata

Custom filter доступен в object-authored API через `ColumnRegistryDefinition::withFilter()`. `normalize()` проверяет и
преобразует transport value; `expression()` возвращает normalized operator/value. Registry SQL compiler поддерживает
фиксированную семантику expression: array становится `IN`, `contains` — parameterized `ILIKE`, `is_null` /
`is_not_null` — null predicate, остальные operators — equality. Expression не выбирает произвольную SQL-колонку:
compiler применяет его к owning column.

```php
final class CsvIntegerFilter extends BaseRegistryFilterDefinition
{
    public function __construct(string $queryKey, string $columnKey, string $title)
    {
        parent::__construct($queryKey, $title, $columnKey, 'integer[]', ['in']);
    }

    public function normalize(mixed $value): mixed
    {
        if (!is_string($value) || preg_match('/^\d+(,\d+)*$/', $value) !== 1) {
            return null;
        }

        return array_map('intval', explode(',', $value));
    }
}

$column = (new ColumnRegistryDefinition('departmentId', 'Подразделение'))
    ->withFilter(new CsvIntegerFilter('department_ids', 'departmentId', 'Подразделения'));
```

Validator считает `null` и пустой array invalid input. Definition обязана сериализовать тот же query key и формат,
которые понимает transport. Attribute frontend `v0.7` поддерживает только встроенные filter Attributes; для custom
filter используйте object API, пока для него не появится отдельный first-class Attribute.

`RegistrySearchDefinitionInterface` настраивает только frontend metadata, query key и enabled flag. Сам SQL search
остаётся поиском по колонкам, отмеченным searchable; этот interface не является произвольным search algorithm. Для
другого алгоритма используйте fixed SQL expression searchable column либо standalone select, а не request SQL.

## Custom Resolver Invoker И Select Compiler

`RegistryResolverInvokerInterface` и `Select\Runtime\ResolverInvokerInterface` — framework adapters. Реализация
получает callable/class-string и именованный argument map, обязана вызвать resolver с values в переданном порядке и
вернуть результат без изменения. В большинстве приложений достаточно встроенных PSR-container adapters; custom
invoker нужен только для другого DI lifecycle. Ошибка resolution/call должна быть выброшена, а не превращена в
`null`.

`SelectCompilerInterface` — порт для альтернативного SQL backend standalone select. Реализация владеет обеими
операциями: `compile()` возвращает rows `CompiledSelect`, `compileReadPlan()` — rows и optional count. Она должна
валидировать `SelectQueryInput`, связывать все dynamic values параметрами и сохранять semantics criteria, runtime
constraints, sorting и pagination. Приложение регистрирует compiler в своём DI и вызывает его явно; TableRegistry не
выбирает dialect по connection string.

```php
final class ProjectSelectReader
{
    public function __construct(private SelectCompilerInterface $compiler)
    {
    }

    public function plan(SelectDefinition $definition, SelectQueryInput $query): CompiledSelectReadPlan
    {
        return $this->compiler->compileReadPlan($definition, $query);
    }
}
```

`PostgresSelectCompiler` — reference implementation. До подключения другого backend проверьте SQL/params, rows/count
parity, invalid input и каждый runtime constraint отдельными consumer tests.

## Проверка Расширения

Минимальный consumer contract test должен фиксировать:

1. место composition и конкретную реализацию interface;
2. успешный result/output;
3. invalid input или неверный return type;
4. empty/no-access path для runtime constraints;
5. одинаковый scope для rows, count и export;
6. отсутствие raw request values в SQL;
7. повторное использование compiled definition без повторного compile-time I/O.

Не опирайтесь на `@internal` symbols. Перед обновлением пакета сверяйте interface signatures с
[`public-api.md`](public-api.md) и запускайте consumer integration suite на реально resolved Composer version.
