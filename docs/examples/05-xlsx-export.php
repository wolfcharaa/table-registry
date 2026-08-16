<?php

declare(strict_types=1);

use TableRegistry\Column\ColumnRegistryDefinition;
use TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use TableRegistry\Definition\TableRegistryDefinition;
use TableRegistry\Export\RegistryExportColumnResolver;
use TableRegistry\Export\RegistryXlsxExporter;
use TableRegistry\Row\RegistryProjectedRowsFlattener;
use TableRegistry\Source\RegistrySourceDefinition;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$definition = new TableRegistryDefinition(
    registryKey: 'users',
    title: 'Пользователи',
    source: RegistrySourceDefinition::from('public.users'),
    columns: [
        new PrimaryKeyColumnRegistryDefinition('id'),
        new ColumnRegistryDefinition('fullName', 'ФИО'),
        new ColumnRegistryDefinition('status', 'Статус'),
    ]
);

$projectedRows = [
    [
        'primaryKey' => '1',
        'values' => [
            'id' => ['value' => 1, 'label' => '1'],
            'fullName' => ['value' => 'Иванов Иван', 'label' => 'Иванов Иван'],
            'status' => ['value' => 'active', 'label' => 'Активен'],
        ],
    ],
];

$columns = (new RegistryExportColumnResolver())->resolve(
    definition: $definition,
    allowedColumnKeys: ['fullName', 'status'],
    requestedColumnKeys: ['status', 'fullName']
);

$flatRows = RegistryProjectedRowsFlattener::flattenLabels($projectedRows);

$result = (new RegistryXlsxExporter())->export(
    definition: $definition,
    columns: $columns,
    rows: $flatRows,
    fileNamePrefix: 'users'
);

echo 'File path: ' . $result->filePath() . PHP_EOL;
echo 'File name: ' . $result->fileName() . PHP_EOL;

