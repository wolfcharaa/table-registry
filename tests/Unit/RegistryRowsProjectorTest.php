<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\ColumnSelectDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Row\RegistryRowData;
use Wolfcharaa\TableRegistry\Row\RegistryRowsProjector;
use Wolfcharaa\TableRegistry\Row\RegistryRowInputDefinition;
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

    public function givesAttributeStyleResolversOnlyDeclaredInputs(): void
    {
        $definition = (new TableRegistryDefinition(
            registryKey: 'devices',
            title: 'Устройства',
            source: RegistrySourceDefinition::from('public.devices d'),
            columns: [
                new PrimaryKeyColumnRegistryDefinition('id'),
                (new ColumnRegistryDefinition('state', 'Статус'))
                    ->withHydration(
                        static fn (
                            PrimitiveValueObjectInterface $value,
                            RegistryRowData $row
                        ): string => \implode(',', \array_keys($row->all())),
                        ['departmentTitle']
                    )
                    ->withLabel(
                        static fn (
                            PrimitiveValueObjectInterface $value,
                            RegistryRowData $row
                        ): string => \implode(',', \array_keys($row->all())),
                        ['departmentCode']
                    ),
            ]
        ))
            ->withRowInput(new RegistryRowInputDefinition(
                'departmentTitle',
                ColumnSelectDefinition::sqlExpression('departmentTitle', 'd.department_title')
            ))
            ->withRowInput(new RegistryRowInputDefinition(
                'departmentCode',
                ColumnSelectDefinition::sqlExpression('departmentCode', 'd.department_code')
            ));

        $rows = (new RegistryRowsProjector())->project($definition, [[
            'id' => 15,
            'state' => 'ready',
            'departmentTitle' => 'Эксплуатация',
            'departmentCode' => 'OPS',
            'unlisted' => 'must stay hidden',
        ]]);

        Assert::same($rows[0]['values']['state']['value'], 'departmentTitle');
        Assert::same($rows[0]['values']['state']['label'], 'departmentCode');
        Assert::same(\array_keys($rows[0]['values']), ['id', 'state']);
    }

    public function keepsLegacyResolverFullRowAccess(): void
    {
        $definition = new TableRegistryDefinition(
            registryKey: 'devices',
            title: 'Устройства',
            source: RegistrySourceDefinition::from('public.devices'),
            columns: [
                new PrimaryKeyColumnRegistryDefinition('id'),
                (new ColumnRegistryDefinition('state', 'Статус'))->withLabel(
                    static fn (
                        PrimitiveValueObjectInterface $value,
                        RegistryRowData $row
                    ): string => \implode(',', \array_keys($row->all()))
                ),
            ]
        );

        $rows = (new RegistryRowsProjector())->project($definition, [[
            'id' => 15,
            'state' => 'ready',
            'legacyExtra' => 'visible to legacy resolver',
        ]]);

        Assert::same($rows[0]['values']['state']['label'], 'id,state,legacyExtra');
    }

    public function projectsExternalRowsWithInternalResolverInputs(): void
    {
        $definition = (new TableRegistryDefinition(
            registryKey: 'object_history',
            title: 'История объекта',
            source: RegistrySourceDefinition::external('object_history'),
            columns: [
                new PrimaryKeyColumnRegistryDefinition('id'),
                (new ColumnRegistryDefinition('type', 'Тип'))->withLabel(
                    static function (
                        PrimitiveValueObjectInterface $value,
                        RegistryRowData $row
                    ): string {
                        $typeTitle = $row->get('typeTitle');
                        if (!\is_string($typeTitle)) {
                            throw new \UnexpectedValueException('External type title must be a string.');
                        }

                        return $typeTitle;
                    },
                    ['typeTitle']
                ),
            ]
        ))->withRowInput(new RegistryRowInputDefinition('typeTitle'));

        $rows = (new RegistryRowsProjector())->project($definition, [[
            'id' => 'event-1',
            'type' => 'updated',
            'typeTitle' => 'Изменение',
        ]]);

        Assert::same($rows[0]['primaryKey'], 'event-1');
        Assert::same($rows[0]['values']['type'], [
            'value' => 'updated',
            'label' => 'Изменение',
        ]);
        Assert::same(\array_keys($rows[0]['values']), ['id', 'type']);
    }
}
