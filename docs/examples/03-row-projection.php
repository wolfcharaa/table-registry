<?php

declare(strict_types=1);

use TableRegistry\Column\ColumnRegistryDefinition;
use TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use TableRegistry\Definition\TableRegistryDefinition;
use TableRegistry\Row\RegistryRowData;
use TableRegistry\Row\RegistryRowsProjector;
use TableRegistry\Source\RegistrySourceDefinition;
use TableRegistry\Value\DateTimeValue;
use TableRegistry\Value\PrimitiveValueObjectInterface;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$definition = new TableRegistryDefinition(
    registryKey: 'events',
    title: 'События',
    source: RegistrySourceDefinition::from('public.events e'),
    columns: [
        new PrimaryKeyColumnRegistryDefinition('id'),
        (new ColumnRegistryDefinition('createdAt', 'Дата события'))
            ->withRowTypeObject(DateTimeValue::class),
        (new ColumnRegistryDefinition('status', 'Статус'))
            ->withLabel(static function (
                PrimitiveValueObjectInterface $value,
                RegistryRowData $row,
                ColumnRegistryDefinition $column
            ): string {
                return match ($value->getValue()) {
                    'success' => 'Успешно',
                    'failed' => 'Ошибка',
                    default => 'Неизвестно',
                };
            }),
    ]
);

$projectedRows = (new RegistryRowsProjector())->project($definition, [
    [
        'id' => 100,
        'createdAt' => '2026-08-16 12:30:00',
        'status' => 'success',
    ],
]);

echo json_encode($projectedRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

