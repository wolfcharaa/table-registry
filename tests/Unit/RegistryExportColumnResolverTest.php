<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Export\RegistryExportColumnResolver;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;

final class RegistryExportColumnResolverTest extends RegistryTestCase
{
    public function testResolvesRequestedColumnsInsideDefinitionExportPolicy(): void
    {
        $columns = (new RegistryExportColumnResolver())->resolve(
            $this->usersDefinition(),
            ['status', 'missing', 'status', 'fullName']
        );

        self::assertSame(['status', 'fullName'], \array_map(
            static fn ($column): string => $column->key(),
            $columns
        ));
    }

    public function testFallsBackToExportableColumnsWhenRequestIsEmpty(): void
    {
        $columns = (new RegistryExportColumnResolver())->resolve($this->usersDefinition());

        self::assertSame(['id', 'fullName', 'status'], \array_map(
            static fn ($column): string => $column->key(),
            $columns
        ));
    }

    public function testSkipsPayloadOnlyAndNonExportableColumns(): void
    {
        $definition = new TableRegistryDefinition(
            registryKey: 'users',
            title: 'Пользователи',
            source: RegistrySourceDefinition::from('public.users'),
            columns: [
                new PrimaryKeyColumnRegistryDefinition('id'),
                new ColumnRegistryDefinition('fullName', 'ФИО'),
                (new ColumnRegistryDefinition('userId', 'ID пользователя'))->withExportable(false),
                (new ColumnRegistryDefinition('actionToken', 'Action token'))->withPayloadOnly(),
            ]
        );

        $columns = (new RegistryExportColumnResolver())->resolve(
            $definition,
            ['actionToken', 'userId', 'fullName']
        );

        self::assertSame(['fullName'], \array_map(
            static fn ($column): string => $column->key(),
            $columns
        ));

        self::assertSame(['id', 'fullName'], $definition->columns()->exportableKeys());
    }
}
