<?php

declare(strict_types=1);

use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\ColumnSelectDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;
use Wolfcharaa\TableRegistry\Sql\PostgresRegistrySqlCompiler;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlCondition;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlQueryInput;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlRuntimeContext;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$definition = new TableRegistryDefinition(
    registryKey: 'documents',
    title: 'Документы',
    source: RegistrySourceDefinition::from('public.documents d'),
    columns: [
        (new PrimaryKeyColumnRegistryDefinition('id'))
            ->withSelect(ColumnSelectDefinition::sqlExpression('id', 'd.id')),
        (new ColumnRegistryDefinition('number', 'Номер'))
            ->withSelect(ColumnSelectDefinition::sqlExpression('number', 'd.number')),
    ]
);

$availableDepartmentIds = [10, 20, 30];

$definition = $definition->withRuntimeConstraint(
    static function (
        RegistrySqlRuntimeContext $context,
        TableRegistryDefinition $definition,
        RegistrySqlQueryInput $query
    ) use ($availableDepartmentIds): ?RegistrySqlCondition {
        if ($availableDepartmentIds === []) {
            return RegistrySqlCondition::impossible();
        }

        $placeholders = [];
        foreach ($availableDepartmentIds as $departmentId) {
            $placeholders[] = ':' . $context->param($departmentId);
        }

        return RegistrySqlCondition::raw('d.department_id IN (' . implode(', ', $placeholders) . ')');
    }
);

$compiled = (new PostgresRegistrySqlCompiler())->compileRows($definition, [
    'page' => 1,
    'per_page' => 25,
]);

echo $compiled->sql() . PHP_EOL;
echo json_encode($compiled->params(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

