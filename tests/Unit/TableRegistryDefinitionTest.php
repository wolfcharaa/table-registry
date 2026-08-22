<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Exception\InvalidRegistryDefinitionException;
use Wolfcharaa\TableRegistry\Sort\RegistryDefaultSortDefinition;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;

final class TableRegistryDefinitionTest extends RegistryTestCase
{
    public function testSerializesFrontendContractWithoutBackendSource(): void
    {
        $payload = $this->usersDefinition()->jsonSerialize();

        self::assertSame(2, $payload['schemaVersion']);
        self::assertSame('users', $payload['registryKey']);
        self::assertSame('Пользователи', $payload['title']);
        self::assertSame('id', $payload['primaryKey']);
        self::assertArrayNotHasKey('source', $payload);
        self::assertSame(['column' => 'id', 'direction' => 'desc'], $payload['query']['defaultSort']);

        $fullNameColumn = $payload['columns'][1];
        self::assertSame('fullName', $fullNameColumn['key']);
        self::assertTrue($fullNameColumn['searchable']);
        self::assertTrue($fullNameColumn['sortable']);
        self::assertTrue($fullNameColumn['filterable']);
        self::assertSame('filter_fullName', $fullNameColumn['filter']['queryKey']);
        self::assertSame(['contains'], $fullNameColumn['filter']['operators']);

        $statusColumn = $payload['columns'][2];
        self::assertSame('static', $statusColumn['filter']['optionSource']);
        self::assertSame('Активен', $statusColumn['filter']['options'][0]['label']);
    }

    public function testRejectsDefinitionWithoutPrimaryKey(): void
    {
        $this->expectException(InvalidRegistryDefinitionException::class);

        new TableRegistryDefinition(
            registryKey: 'invalid',
            title: 'Некорректный реестр',
            source: RegistrySourceDefinition::from('public.invalid'),
            columns: [
                new ColumnRegistryDefinition('name', 'Название'),
            ]
        );
    }

    public function testRejectsDuplicateColumnKeys(): void
    {
        $this->expectException(InvalidRegistryDefinitionException::class);

        new TableRegistryDefinition(
            registryKey: 'invalid',
            title: 'Некорректный реестр',
            source: RegistrySourceDefinition::from('public.invalid'),
            columns: [
                new PrimaryKeyColumnRegistryDefinition('id'),
                new ColumnRegistryDefinition('id', 'Дубликат'),
            ]
        );
    }

    public function testHidesPayloadOnlyColumnsFromFrontendContract(): void
    {
        $payload = (new TableRegistryDefinition(
            registryKey: 'devices',
            title: 'Устройства',
            source: RegistrySourceDefinition::from('public.devices'),
            columns: [
                new PrimaryKeyColumnRegistryDefinition('id'),
                new ColumnRegistryDefinition('title', 'Название'),
                (new ColumnRegistryDefinition('actionToken', 'Action token'))
                    ->withPayloadOnly(),
            ]
        ))->jsonSerialize();

        self::assertSame(['id', 'title'], \array_map(
            static fn (array $column): string => $column['key'],
            $payload['columns']
        ));
    }

    public function testRejectsPayloadOnlyPrimaryKey(): void
    {
        $this->expectException(InvalidRegistryDefinitionException::class);

        new TableRegistryDefinition(
            registryKey: 'invalid',
            title: 'Некорректный реестр',
            source: RegistrySourceDefinition::from('public.invalid'),
            columns: [
                (new PrimaryKeyColumnRegistryDefinition('id'))->withPayloadOnly(),
                new ColumnRegistryDefinition('title', 'Название'),
            ]
        );
    }

    public function testKeepsOutputOnlyColumnsInFrontendContract(): void
    {
        $payload = (new TableRegistryDefinition(
            registryKey: 'people',
            title: 'Люди',
            source: RegistrySourceDefinition::from('public.people'),
            columns: [
                new PrimaryKeyColumnRegistryDefinition('id'),
                (new ColumnRegistryDefinition('displayName', 'ФИО'))
                    ->withOutputOnly(),
            ]
        ))->jsonSerialize();

        self::assertSame(['id', 'displayName'], \array_map(
            static fn (array $column): string => $column['key'],
            $payload['columns']
        ));

        self::assertFalse($payload['columns'][1]['searchable']);
        self::assertFalse($payload['columns'][1]['sortable']);
        self::assertFalse($payload['columns'][1]['filterable']);
    }

    public function testRejectsOutputOnlyPrimaryKey(): void
    {
        $this->expectException(InvalidRegistryDefinitionException::class);

        new TableRegistryDefinition(
            registryKey: 'invalid',
            title: 'Некорректный реестр',
            source: RegistrySourceDefinition::from('public.invalid'),
            columns: [
                (new PrimaryKeyColumnRegistryDefinition('id'))->withOutputOnly(),
                new ColumnRegistryDefinition('title', 'Название'),
            ]
        );
    }

    public function testRejectsDefaultSortByUnknownColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->usersDefinition()->withDefaultSort(RegistryDefaultSortDefinition::asc('missing'));
    }
}
