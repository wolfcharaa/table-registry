<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Support;

use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\ColumnSelectDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\DateRangeFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\SelectFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\TextFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Query\RegistryPaginationDefinition;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlCondition;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlQueryInput;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlRuntimeContext;
use Wolfcharaa\TableRegistry\Sort\RegistryDefaultSortDefinition;
use Wolfcharaa\TableRegistry\Sort\RegistrySortDefinition;
use Wolfcharaa\TableRegistry\Source\RegistryDataScopeDefinition;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;
use Wolfcharaa\TableRegistry\Value\DateTimeValue;
use Wolfcharaa\TableRegistry\Value\IntegerValue;
use Wolfcharaa\TableRegistry\Value\PrimitiveValueObjectInterface;

final class LegacyRegistryFixture
{
    public static function definition(): TableRegistryDefinition
    {
        return (new TableRegistryDefinition(
            registryKey: 'legacy_people',
            title: 'Люди',
            source: RegistrySourceDefinition::from('public.people p')
                ->withLeftJoin('public.department d', 'd.id = p.department_id')
                ->withConnection('reporting')
                ->withCountSource('public.people_count_source p')
                ->withDataScope(RegistryDataScopeDefinition::named('area', areaId: 77)),
            columns: [
                (new PrimaryKeyColumnRegistryDefinition('id'))
                    ->withSelect(ColumnSelectDefinition::sqlExpression('id', 'p.id')),
                (new ColumnRegistryDefinition('fullName', 'ФИО'))
                    ->withSelect(ColumnSelectDefinition::sqlExpression(
                        'fullName',
                        "concat_ws(' ', p.last_name, p.first_name, p.middle_name)"
                    ))
                    ->withAuxiliarySelect(ColumnSelectDefinition::sqlExpression('departmentCode', 'd.code'))
                    ->withSearchable()
                    ->withSort(RegistrySortDefinition::byExpression('fullName', 'p.last_name, p.first_name'))
                    ->withFilter(TextFilterRegistryDefinition::contains('filter_fullName', 'fullName', 'ФИО'))
                    ->withHint('Поиск по ФИО'),
                (new ColumnRegistryDefinition('status', 'Статус'))
                    ->withSelect(ColumnSelectDefinition::sqlExpression('status', 'p.status'))
                    ->withFilter(SelectFilterRegistryDefinition::static(
                        queryKey: 'filter_status',
                        columnKey: 'status',
                        title: 'Статус',
                        options: [
                            ['value' => 'active', 'label' => 'Активен'],
                            ['value' => 'blocked', 'label' => 'Заблокирован'],
                        ]
                    ))
                    ->withLabel(static fn (PrimitiveValueObjectInterface $value): string => match ($value->getValue()) {
                        'active' => 'Активен',
                        'blocked' => 'Заблокирован',
                        default => 'Неизвестно',
                    }),
                (new ColumnRegistryDefinition('organizationId', 'Организация'))
                    ->withSelect(ColumnSelectDefinition::sqlExpression('organizationId', 'p.organization_id'))
                    ->withFilter(SelectFilterRegistryDefinition::preload(
                        'filter_organizationId',
                        'organizationId',
                        'Организация',
                        '/options/organizations',
                        'integer'
                    ))
                    ->withRowTypeObject(IntegerValue::class)
                    ->withHydration(static fn (IntegerValue $value): string => 'organization:' . ($value->getValue() ?? ''))
                    ->withLabel(static fn (IntegerValue $value): string => 'Организация ' . ($value->getValue() ?? '')),
                (new ColumnRegistryDefinition('areaId', 'Территория'))
                    ->withSelect(ColumnSelectDefinition::sqlExpression('areaId', 'p.area_id'))
                    ->withFilter(SelectFilterRegistryDefinition::async(
                        'filter_areaId',
                        'areaId',
                        'Территория',
                        '/options/areas',
                        'integer'
                    ))
                    ->withExportable(false),
                (new ColumnRegistryDefinition('createdAt', 'Создан'))
                    ->withSelect(ColumnSelectDefinition::sqlExpression('createdAt', 'p.created_at'))
                    ->withFilter(DateRangeFilterRegistryDefinition::forColumn('createdAt', 'Создан'))
                    ->withRowTypeObject(DateTimeValue::class),
                (new ColumnRegistryDefinition('actionToken', 'Action token'))
                    ->withSelect(ColumnSelectDefinition::sqlExpression('actionToken', 'p.action_token'))
                    ->withPayloadOnly(),
                (new ColumnRegistryDefinition('displayName', 'Отображаемое имя'))
                    ->withOutputOnly(),
            ]
        ))
            ->withPagination(new RegistryPaginationDefinition(defaultPerPage: 20, maxPerPage: 100))
            ->withDefaultSort(RegistryDefaultSortDefinition::desc('id'))
            ->withRuntimeConstraint(static function (RegistrySqlRuntimeContext $context): RegistrySqlCondition {
                return RegistrySqlCondition::raw('p.area_id = :' . $context->param(77));
            });
    }

    /** @return array<string, mixed> */
    public static function rawQuery(): array
    {
        return [
            'search' => 'иван',
            'filter_fullName' => 'петров',
            'filter_status' => ['active', 'blocked'],
            'createdAt_from' => '2026-10-01',
            'createdAt_to' => '2026-10-04',
            RegistrySqlQueryInput::SORT_COLUMN_KEY => 'fullName',
            RegistrySqlQueryInput::SORT_DIRECTION_KEY => 'desc',
            'page' => 2,
            'per_page' => 10,
            'selected_primary_keys' => ['42'],
        ];
    }

    public static function query(): RegistrySqlQueryInput
    {
        return RegistrySqlQueryInput::fromArray(self::definition(), self::rawQuery());
    }

    /** @return list<array<string, mixed>> */
    public static function databaseRows(): array
    {
        return [[
            'id' => 42,
            'fullName' => 'Петров Иван Иванович',
            'departmentCode' => 'OPS',
            'status' => 'active',
            'organizationId' => '7',
            'areaId' => 77,
            'createdAt' => '2026-10-04 12:30:00',
            'actionToken' => 'token-42',
            'displayName' => 'И. И. Петров',
        ]];
    }

    public static function json(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . PHP_EOL;
    }
}
