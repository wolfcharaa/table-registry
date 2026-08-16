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

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$definition = (new TableRegistryDefinition(
    registryKey: 'users',
    title: 'Пользователи',
    source: RegistrySourceDefinition::from('public.users u')
        ->withLeftJoin('public.user_profiles p', 'p.user_id = u.id')
        ->withCountSource('users'),
    columns: [
        (new PrimaryKeyColumnRegistryDefinition('id'))
            ->withSelect(ColumnSelectDefinition::sqlExpression('id', 'u.id')),

        (new ColumnRegistryDefinition('fullName', 'ФИО'))
            ->withSelect(ColumnSelectDefinition::sqlExpression(
                'fullName',
                "concat_ws(' ', p.last_name, p.first_name, p.middle_name)"
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

echo json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

