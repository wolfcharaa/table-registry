<?php

declare(strict_types=1);

use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\ColumnSelectDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\SelectFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\TextFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Sort\RegistryDefaultSortDefinition;
use Wolfcharaa\TableRegistry\Sort\RegistrySortDefinition;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;
use Wolfcharaa\TableRegistry\Sql\PostgresRegistrySqlCompiler;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlQueryInput;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$definition = (new TableRegistryDefinition(
    registryKey: 'users',
    title: 'Пользователи',
    source: RegistrySourceDefinition::from('public.users u')
        ->withLeftJoin('public.user_profiles p', 'p.user_id = u.id'),
    columns: [
        (new PrimaryKeyColumnRegistryDefinition('id'))
            ->withSelect(ColumnSelectDefinition::sqlExpression('id', 'u.id')),
        (new ColumnRegistryDefinition('fullName', 'ФИО'))
            ->withSelect(ColumnSelectDefinition::sqlExpression(
                'fullName',
                "concat_ws(' ', p.last_name, p.first_name)"
            ))
            ->withSearchable()
            ->withSort(RegistrySortDefinition::byExpression('fullName', 'p.last_name, p.first_name'))
            ->withFilter(TextFilterRegistryDefinition::contains('filter_fullName', 'fullName', 'ФИО')),
        (new ColumnRegistryDefinition('status', 'Статус'))
            ->withSelect(ColumnSelectDefinition::sqlExpression('status', 'u.status'))
            ->withFilter(SelectFilterRegistryDefinition::static('filter_status', 'status', 'Статус')),
    ]
))->withDefaultSort(RegistryDefaultSortDefinition::desc('id'));

$query = [
    'search' => 'иван',
    'filter_status' => ['active', 'blocked'],
    RegistrySqlQueryInput::SORT_COLUMN_KEY => 'fullName',
    RegistrySqlQueryInput::SORT_DIRECTION_KEY => 'desc',
    'page' => 2,
    'per_page' => 25,
];

$compiler = new PostgresRegistrySqlCompiler();
$rows = $compiler->compileRows($definition, $query);
$count = $compiler->compileCount($definition, $query);
$exportRows = $compiler->compileExportRows($definition, $query);

echo "ROWS SQL:\n" . $rows->sql() . "\n";
echo "ROWS PARAMS:\n" . json_encode($rows->params(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "COUNT SQL:\n" . $count->sql() . "\n\n";
echo "EXPORT SQL WITHOUT PAGINATION:\n" . $exportRows->sql() . "\n";

