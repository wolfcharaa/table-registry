<?php

declare(strict_types=1);

namespace TableRegistry\Tests\Unit;

use TableRegistry\Column\ColumnRegistryDefinition;
use TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use TableRegistry\Definition\TableRegistryDefinition;
use TableRegistry\Row\RegistryRowData;
use TableRegistry\Row\RegistryRowsProjector;
use TableRegistry\Source\RegistrySourceDefinition;
use TableRegistry\Value\DateTimeValue;
use TableRegistry\Value\PrimitiveValueObjectInterface;

final class RegistryRowsProjectorTest extends RegistryTestCase
{
    public function testProjectsRowsToStableValueAndLabelPayload(): void
    {
        $definition = new TableRegistryDefinition(
            registryKey: 'events',
            title: 'События',
            source: RegistrySourceDefinition::from('public.events e'),
            columns: [
                new PrimaryKeyColumnRegistryDefinition('id'),
                (new ColumnRegistryDefinition('createdAt', 'Дата'))
                    ->withRowTypeObject(DateTimeValue::class),
                (new ColumnRegistryDefinition('state', 'Статус'))
                    ->withLabel(static function (
                        PrimitiveValueObjectInterface $value,
                        RegistryRowData $row,
                        ColumnRegistryDefinition $column
                    ): string {
                        return $value->getValue() === 'ready' ? 'Готово' : 'Неизвестно';
                    }),
            ]
        );

        $rows = (new RegistryRowsProjector())->project($definition, [
            [
                'id' => 7,
                'createdAt' => '2026-08-15 17:30:10',
                'state' => 'ready',
            ],
        ]);

        self::assertSame('7', $rows[0]['primaryKey']);
        self::assertSame('2026-08-15 17:30:10', $rows[0]['values']['createdAt']['value']);
        self::assertSame('15.08.2026 17:30', $rows[0]['values']['createdAt']['label']);
        self::assertSame('ready', $rows[0]['values']['state']['value']);
        self::assertSame('Готово', $rows[0]['values']['state']['label']);
    }
}

