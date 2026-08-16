<?php

declare(strict_types=1);

namespace TableRegistry\Tests\Unit;

use TableRegistry\Column\ColumnRegistryDefinition;
use TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use TableRegistry\Definition\TableRegistryDefinition;
use TableRegistry\Exception\InvalidRegistryDefinitionException;
use TableRegistry\Sort\RegistryDefaultSortDefinition;
use TableRegistry\Source\RegistrySourceDefinition;

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

    public function testRejectsDefaultSortByUnknownColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->usersDefinition()->withDefaultSort(RegistryDefaultSortDefinition::asc('missing'));
    }
}

