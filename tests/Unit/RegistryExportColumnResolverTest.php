<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Export\RegistryExportColumnResolver;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;

#[Test]
final class RegistryExportColumnResolverTest extends RegistryTestCase
{
    public function resolvesRequestedColumnsInsideDefinitionExportPolicy(): void
    {
        $columns = (new RegistryExportColumnResolver())->resolve(
            $this->usersDefinition(),
            ['status', 'missing', 'status', 'fullName']
        );

        Assert::same(\array_map(
            static fn ($column): string => $column->key(),
            $columns
        ), ['status', 'fullName']);
    }

    public function fallsBackToExportableColumnsWhenRequestIsEmpty(): void
    {
        $columns = (new RegistryExportColumnResolver())->resolve($this->usersDefinition());

        Assert::same(\array_map(
            static fn ($column): string => $column->key(),
            $columns
        ), ['id', 'fullName', 'status']);
    }

    public function skipsPayloadOnlyAndNonExportableColumns(): void
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
                (new ColumnRegistryDefinition('computedLabel', 'Computed label'))->withOutputOnly(),
            ]
        );

        $columns = (new RegistryExportColumnResolver())->resolve(
            $definition,
            ['actionToken', 'userId', 'computedLabel', 'fullName']
        );

        Assert::same(\array_map(
            static fn ($column): string => $column->key(),
            $columns
        ), ['fullName']);

        Assert::same($definition->columns()->exportableKeys(), ['id', 'fullName']);
    }
}
