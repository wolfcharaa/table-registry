<?php

declare(strict_types=1);

namespace TableRegistry\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TableRegistry\Column\ColumnRegistryDefinition;
use TableRegistry\Column\ColumnSelectDefinition;
use TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use TableRegistry\Definition\TableRegistryDefinition;
use TableRegistry\Filter\SelectFilterRegistryDefinition;
use TableRegistry\Filter\TextFilterRegistryDefinition;
use TableRegistry\Sort\RegistryDefaultSortDefinition;
use TableRegistry\Sort\RegistrySortDefinition;
use TableRegistry\Source\RegistrySourceDefinition;

abstract class RegistryTestCase extends TestCase
{
    protected function usersDefinition(): TableRegistryDefinition
    {
        return (new TableRegistryDefinition(
            registryKey: 'users',
            title: 'Пользователи',
            source: RegistrySourceDefinition::from('public.users u')
                ->withLeftJoin('public.user_profile p', 'p.user_id = u.id'),
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
                    ->withFilter(TextFilterRegistryDefinition::contains(
                        queryKey: 'filter_fullName',
                        columnKey: 'fullName',
                        title: 'ФИО'
                    )),
                (new ColumnRegistryDefinition('status', 'Статус'))
                    ->withSelect(ColumnSelectDefinition::sqlExpression('status', 'u.status'))
                    ->withFilter(SelectFilterRegistryDefinition::static(
                        queryKey: 'filter_status',
                        columnKey: 'status',
                        title: 'Статус',
                        options: [
                            ['value' => 'active', 'label' => 'Активен'],
                            ['value' => 'blocked', 'label' => 'Заблокирован'],
                        ]
                    )),
            ]
        ))->withDefaultSort(RegistryDefaultSortDefinition::desc('id'));
    }
}

