<?php

declare(strict_types=1);

use Wolfcharaa\TableRegistry\Option\OptionListDefinition;
use Wolfcharaa\TableRegistry\Option\SelectOptionsProjector;
use Wolfcharaa\TableRegistry\Select\Definition\SelectCriterionDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectFieldDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectOrderDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectPaginationDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectSearchDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectSourceDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectValueType;
use Wolfcharaa\TableRegistry\Select\Query\SelectQueryInput;
use Wolfcharaa\TableRegistry\Select\Query\SelectQueryValidator;
use Wolfcharaa\TableRegistry\Select\Row\SelectRowsProjector;
use Wolfcharaa\TableRegistry\Select\Runtime\SelectRuntimeContext;
use Wolfcharaa\TableRegistry\Select\Sql\PostgresSelectCompiler;
use Wolfcharaa\TableRegistry\Select\Sql\SelectCondition;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$select = SelectDefinition::from(
    SelectSourceDefinition::relation('public.organization', 'o'),
    [
        SelectFieldDefinition::column('o.id', 'value'),
        SelectFieldDefinition::column('o.name', 'label'),
        SelectFieldDefinition::column('o.code', 'code'),
    ]
)
    ->withCriterion(SelectCriterionDefinition::equal(
        'departmentId',
        'o.department_id',
        SelectValueType::Integer
    ))
    ->withSearch(SelectSearchDefinition::contains(['o.name', 'o.code'], maxLength: 100))
    ->withOrderBy(SelectOrderDefinition::ascending('o.name'))
    ->withOrderBy(SelectOrderDefinition::ascending('o.id'))
    ->withPagination(SelectPaginationDefinition::limit(defaultLimit: 25, maxLimit: 100))
    ->withRuntimeConstraint(
        static function (SelectRuntimeContext $context): SelectCondition {
            return SelectCondition::sql('o.area_id = ' . $context->param(77));
        }
    );

$options = OptionListDefinition::from(
    select: $select,
    valueAlias: 'value',
    labelAlias: 'label',
    valueType: SelectValueType::Integer
)->withCode('code');

// HTTP aliases and scalar coercion end in the application adapter.
$query = SelectQueryInput::from(
    search: 'центр',
    criteria: ['departmentId' => 10],
    limit: 25
);

(new SelectQueryValidator())->validate($select, $query)->assertValid();
$compiled = (new PostgresSelectCompiler())->compile($select, $query);

// Execute $compiled->sql() with $compiled->params() in the application DB adapter.
$databaseRows = [
    ['value' => '42', 'label' => 'Центральная организация', 'code' => null],
];

$rows = (new SelectRowsProjector())->project($select, $databaseRows);
$projectedOptions = (new SelectOptionsProjector())->project($options, $rows);

echo $compiled->sql() . PHP_EOL;
echo json_encode($compiled->params(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo json_encode($projectedOptions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
