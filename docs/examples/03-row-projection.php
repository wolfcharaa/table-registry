<?php

declare(strict_types=1);

use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Row\RegistryRowData;
use Wolfcharaa\TableRegistry\Row\RegistryRowsProjector;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;
use Wolfcharaa\TableRegistry\Value\DateTimeValue;
use Wolfcharaa\TableRegistry\Value\PrimitiveValueObjectInterface;

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

