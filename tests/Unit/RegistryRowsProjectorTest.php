<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Row\RegistryRowData;
use Wolfcharaa\TableRegistry\Row\RegistryRowsProjector;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;
use Wolfcharaa\TableRegistry\Value\DateTimeValue;
use Wolfcharaa\TableRegistry\Value\PrimitiveValueObjectInterface;

#[Test]
final class RegistryRowsProjectorTest extends RegistryTestCase
{
    public function projectsRowsToStableValueAndLabelPayload(): void
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

        Assert::same($rows[0]['primaryKey'], '7');
        Assert::same($rows[0]['values']['createdAt']['value'], '2026-08-15 17:30:10');
        Assert::same($rows[0]['values']['createdAt']['label'], '15.08.2026 17:30');
        Assert::same($rows[0]['values']['state']['value'], 'ready');
        Assert::same($rows[0]['values']['state']['label'], 'Готово');
    }

    public function keepsPayloadOnlyColumnsInRowsPayload(): void
    {
        $definition = new TableRegistryDefinition(
            registryKey: 'devices',
            title: 'Устройства',
            source: RegistrySourceDefinition::from('public.devices'),
            columns: [
                new PrimaryKeyColumnRegistryDefinition('id'),
                new ColumnRegistryDefinition('title', 'Название'),
                (new ColumnRegistryDefinition('actionToken', 'Action token'))
                    ->withPayloadOnly(),
            ]
        );

        $rows = (new RegistryRowsProjector())->project($definition, [
            [
                'id' => 15,
                'title' => 'Планшет',
                'actionToken' => 'token-15',
            ],
        ]);

        Assert::same($rows[0]['values']['actionToken']['value'], 'token-15');
        Assert::same($rows[0]['values']['actionToken']['label'], 'token-15');
    }

    public function projectsOutputOnlyColumnsFromPreparedRows(): void
    {
        $definition = new TableRegistryDefinition(
            registryKey: 'people',
            title: 'Люди',
            source: RegistrySourceDefinition::from('public.people'),
            columns: [
                new PrimaryKeyColumnRegistryDefinition('id'),
                new ColumnRegistryDefinition('firstName', 'Имя'),
                (new ColumnRegistryDefinition('displayName', 'ФИО'))
                    ->withOutputOnly(),
            ]
        );

        $rows = (new RegistryRowsProjector())->project($definition, [
            [
                'id' => 15,
                'firstName' => 'Иван',
                'displayName' => 'Иван Петров',
            ],
        ]);

        Assert::same($rows[0]['values']['displayName']['value'], 'Иван Петров');
        Assert::same($rows[0]['values']['displayName']['label'], 'Иван Петров');
    }
}
