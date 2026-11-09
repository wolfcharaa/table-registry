# Public API

Этот документ задаёт поддерживаемую public API surface библиотеки. Наличие PHP-файла под `src/` и доступность
символа через Composer PSR-4 сами по себе не являются compatibility promise.

## Compatibility policy

- Символы из allowlist ниже поддерживаются в пределах линии `v0.6.x`.
- Изменение их public/protected native signatures требует осознанного API review и обновления signature snapshot.
- Символы с `@internal` могут меняться без compatibility promise и не должны использоваться приложениями.
- Каждый новый symbol под `src/` обязан быть либо перечислен здесь и помечен `@api`, либо помечен `@internal`.
- Public/protected signatures не могут ссылаться на internal types.
- Статус существующих API families и направление их миграции описаны в [BC matrix](v0.6-bc-matrix.md).

## Supported symbols

Блок между markers читается contract test-ом. Список должен оставаться уникальным и лексикографически
отсортированным.

<!-- public-api:start -->
- `Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition`
- `Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinitionCollection`
- `Wolfcharaa\TableRegistry\Column\ColumnSelectDefinition`
- `Wolfcharaa\TableRegistry\Column\ColumnValueHydratorInterface`
- `Wolfcharaa\TableRegistry\Column\ColumnValueLabelResolverInterface`
- `Wolfcharaa\TableRegistry\Column\DateTimeValueLabelResolver`
- `Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition`
- `Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition`
- `Wolfcharaa\TableRegistry\Exception\InvalidRegistryDefinitionException`
- `Wolfcharaa\TableRegistry\Exception\InvalidRegistryQueryException`
- `Wolfcharaa\TableRegistry\Exception\InvalidSelectDefinitionException`
- `Wolfcharaa\TableRegistry\Exception\InvalidSelectQueryException`
- `Wolfcharaa\TableRegistry\Exception\OptionProjectionException`
- `Wolfcharaa\TableRegistry\Exception\SelectCompilationException`
- `Wolfcharaa\TableRegistry\Exception\SelectProjectionException`
- `Wolfcharaa\TableRegistry\Exception\UnsupportedRegistrySourceException`
- `Wolfcharaa\TableRegistry\Export\RegistryExportColumnResolver`
- `Wolfcharaa\TableRegistry\Export\RegistryXlsxExportResult`
- `Wolfcharaa\TableRegistry\Export\RegistryXlsxExporter`
- `Wolfcharaa\TableRegistry\Filter\BaseRegistryFilterDefinition`
- `Wolfcharaa\TableRegistry\Filter\BasicRegistryFilterExpression`
- `Wolfcharaa\TableRegistry\Filter\BooleanFilterRegistryDefinition`
- `Wolfcharaa\TableRegistry\Filter\DateRangeFilterRegistryDefinition`
- `Wolfcharaa\TableRegistry\Filter\IntegerFilterRegistryDefinition`
- `Wolfcharaa\TableRegistry\Filter\NullabilityFilterRegistryDefinition`
- `Wolfcharaa\TableRegistry\Filter\RegistryFilterDefinitionInterface`
- `Wolfcharaa\TableRegistry\Filter\RegistryFilterExpressionInterface`
- `Wolfcharaa\TableRegistry\Filter\SelectFilterRegistryDefinition`
- `Wolfcharaa\TableRegistry\Filter\TextFilterRegistryDefinition`
- `Wolfcharaa\TableRegistry\Filter\TimeRangeFilterRegistryDefinition`
- `Wolfcharaa\TableRegistry\Option\OptionListDefinition`
- `Wolfcharaa\TableRegistry\Option\OptionSourceDefinition`
- `Wolfcharaa\TableRegistry\Option\SelectOption`
- `Wolfcharaa\TableRegistry\Option\SelectOptionsProjector`
- `Wolfcharaa\TableRegistry\Query\RegistryPaginationDefinition`
- `Wolfcharaa\TableRegistry\Query\RegistryQueryValidationIssue`
- `Wolfcharaa\TableRegistry\Query\RegistryQueryValidationResult`
- `Wolfcharaa\TableRegistry\Query\RegistryQueryValidator`
- `Wolfcharaa\TableRegistry\Row\RegistryProjectedRowsFlattener`
- `Wolfcharaa\TableRegistry\Row\RegistryRowData`
- `Wolfcharaa\TableRegistry\Row\RegistryRowInputDefinition`
- `Wolfcharaa\TableRegistry\Row\RegistryRowsProjector`
- `Wolfcharaa\TableRegistry\Row\RegistryRowsResult`
- `Wolfcharaa\TableRegistry\Runtime\NativeRegistryResolverInvoker`
- `Wolfcharaa\TableRegistry\Runtime\PsrContainerRegistryResolverInvoker`
- `Wolfcharaa\TableRegistry\Runtime\RegistryResolverInvokerInterface`
- `Wolfcharaa\TableRegistry\Search\RegistrySearchDefinitionInterface`
- `Wolfcharaa\TableRegistry\Search\SearchBySearchableColumnsDefinition`
- `Wolfcharaa\TableRegistry\Select\Definition\SelectCountDefinition`
- `Wolfcharaa\TableRegistry\Select\Definition\SelectCriterionDefinition`
- `Wolfcharaa\TableRegistry\Select\Definition\SelectDefinition`
- `Wolfcharaa\TableRegistry\Select\Definition\SelectFieldDefinition`
- `Wolfcharaa\TableRegistry\Select\Definition\SelectJoinDefinition`
- `Wolfcharaa\TableRegistry\Select\Definition\SelectOrderDefinition`
- `Wolfcharaa\TableRegistry\Select\Definition\SelectPaginationDefinition`
- `Wolfcharaa\TableRegistry\Select\Definition\SelectSearchDefinition`
- `Wolfcharaa\TableRegistry\Select\Definition\SelectSortDefinition`
- `Wolfcharaa\TableRegistry\Select\Definition\SelectSourceDefinition`
- `Wolfcharaa\TableRegistry\Select\Definition\SelectValueType`
- `Wolfcharaa\TableRegistry\Select\Query\SelectNullability`
- `Wolfcharaa\TableRegistry\Select\Query\SelectQueryInput`
- `Wolfcharaa\TableRegistry\Select\Query\SelectQueryValidationIssue`
- `Wolfcharaa\TableRegistry\Select\Query\SelectQueryValidationResult`
- `Wolfcharaa\TableRegistry\Select\Query\SelectQueryValidator`
- `Wolfcharaa\TableRegistry\Select\Query\SelectRange`
- `Wolfcharaa\TableRegistry\Select\Query\SelectSortDirection`
- `Wolfcharaa\TableRegistry\Select\Row\SelectRow`
- `Wolfcharaa\TableRegistry\Select\Row\SelectRowsProjector`
- `Wolfcharaa\TableRegistry\Select\Runtime\NativeResolverInvoker`
- `Wolfcharaa\TableRegistry\Select\Runtime\PsrContainerResolverInvoker`
- `Wolfcharaa\TableRegistry\Select\Runtime\ResolverInvokerInterface`
- `Wolfcharaa\TableRegistry\Select\Runtime\SelectRuntimeConstraintInterface`
- `Wolfcharaa\TableRegistry\Select\Runtime\SelectRuntimeContext`
- `Wolfcharaa\TableRegistry\Select\Sql\CompiledSelect`
- `Wolfcharaa\TableRegistry\Select\Sql\CompiledSelectReadPlan`
- `Wolfcharaa\TableRegistry\Select\Sql\PostgresSelectCompiler`
- `Wolfcharaa\TableRegistry\Select\Sql\SelectCompilerInterface`
- `Wolfcharaa\TableRegistry\Select\Sql\SelectCondition`
- `Wolfcharaa\TableRegistry\Sort\RegistryDefaultSortDefinition`
- `Wolfcharaa\TableRegistry\Sort\RegistrySortDefinition`
- `Wolfcharaa\TableRegistry\Source\RegistryDataScopeDefinition`
- `Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition`
- `Wolfcharaa\TableRegistry\Source\RegistrySourceKind`
- `Wolfcharaa\TableRegistry\Sql\CompiledRegistrySql`
- `Wolfcharaa\TableRegistry\Sql\PostgresIdentifier`
- `Wolfcharaa\TableRegistry\Sql\PostgresLiteral`
- `Wolfcharaa\TableRegistry\Sql\PostgresRegistrySqlCompiler`
- `Wolfcharaa\TableRegistry\Sql\RegistrySqlCondition`
- `Wolfcharaa\TableRegistry\Sql\RegistrySqlQueryInput`
- `Wolfcharaa\TableRegistry\Sql\RegistrySqlRuntimeConstraintInterface`
- `Wolfcharaa\TableRegistry\Sql\RegistrySqlRuntimeContext`
- `Wolfcharaa\TableRegistry\Value\BooleanValue`
- `Wolfcharaa\TableRegistry\Value\DateTimeValue`
- `Wolfcharaa\TableRegistry\Value\IntegerValue`
- `Wolfcharaa\TableRegistry\Value\JsonListValue`
- `Wolfcharaa\TableRegistry\Value\PrimitiveValueObjectInterface`
- `Wolfcharaa\TableRegistry\Value\RawValue`
- `Wolfcharaa\TableRegistry\Value\StringValue`
<!-- public-api:end -->

## Extension ports

Текущие application extension points:

- `ColumnValueHydratorInterface`;
- `ColumnValueLabelResolverInterface`;
- `RegistryFilterDefinitionInterface` и `RegistryFilterExpressionInterface`;
- `RegistryResolverInvokerInterface`;
- `RegistrySearchDefinitionInterface`;
- `RegistrySqlRuntimeConstraintInterface`;
- `ResolverInvokerInterface`;
- `SelectCompilerInterface`;
- `SelectRuntimeConstraintInterface`.

Новые interfaces добавляются только для доказанного project extension use case. Простое желание заменить immutable
value object не является основанием создавать для него interface.
