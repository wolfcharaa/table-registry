<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\ColumnSelectDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Exception\InvalidRegistryDefinitionException;
use Wolfcharaa\TableRegistry\Row\RegistryRowInputDefinition;
use Wolfcharaa\TableRegistry\Sort\RegistryDefaultSortDefinition;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;

#[Test]
final class TableRegistryDefinitionTest extends RegistryTestCase
{
    public function serializesFrontendContractWithoutBackendSource(): void
    {
        $payload = $this->usersDefinition()->jsonSerialize();

        Assert::same($payload['schemaVersion'], 2);
        Assert::same($payload['registryKey'], 'users');
        Assert::same($payload['title'], 'Пользователи');
        Assert::same($payload['primaryKey'], 'id');
        Assert::array($payload)->doesNotHaveKeys('source');
        Assert::same($payload['query']['defaultSort'] ?? null, ['column' => 'id', 'direction' => 'desc']);

        $fullNameColumn = $payload['columns'][1];
        Assert::same($fullNameColumn['key'], 'fullName');
        Assert::true($fullNameColumn['searchable']);
        Assert::true($fullNameColumn['sortable']);
        Assert::true($fullNameColumn['filterable']);
        $fullNameFilter = self::arrayValue($fullNameColumn['filter'] ?? null);
        Assert::same($fullNameFilter['queryKey'] ?? null, 'filter_fullName');
        Assert::same($fullNameFilter['operators'] ?? null, ['contains']);

        $statusColumn = $payload['columns'][2];
        $statusFilter = self::arrayValue($statusColumn['filter'] ?? null);
        Assert::same($statusFilter['optionSource'] ?? null, 'static');
        $options = self::arrayValue($statusFilter['options'] ?? null);
        $firstOption = self::arrayValue($options[0] ?? null);
        Assert::same($firstOption['label'] ?? null, 'Активен');
    }

    public function rejectsDefinitionWithoutPrimaryKey(): void
    {
        Expect::exception(InvalidRegistryDefinitionException::class);

        new TableRegistryDefinition(
            registryKey: 'invalid',
            title: 'Некорректный реестр',
            source: RegistrySourceDefinition::from('public.invalid'),
            columns: [
                new ColumnRegistryDefinition('name', 'Название'),
            ]
        );
    }

    public function rejectsDuplicateColumnKeys(): void
    {
        Expect::exception(InvalidRegistryDefinitionException::class);

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

    public function hidesPayloadOnlyColumnsFromFrontendContract(): void
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

        Assert::same(\array_column($payload['columns'], 'key'), ['id', 'title']);
    }

    public function rejectsPayloadOnlyPrimaryKey(): void
    {
        Expect::exception(InvalidRegistryDefinitionException::class);

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

    public function keepsOutputOnlyColumnsInFrontendContract(): void
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

        Assert::same(\array_column($payload['columns'], 'key'), ['id', 'displayName']);

        Assert::false($payload['columns'][1]['searchable']);
        Assert::false($payload['columns'][1]['sortable']);
        Assert::false($payload['columns'][1]['filterable']);
    }

    public function rejectsOutputOnlyPrimaryKey(): void
    {
        Expect::exception(InvalidRegistryDefinitionException::class);

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

    public function rejectsDefaultSortByUnknownColumn(): void
    {
        Expect::exception(\InvalidArgumentException::class);

        $this->usersDefinition()->withDefaultSort(RegistryDefaultSortDefinition::asc('missing'));
    }

    public function addsInternalRowInputWithoutChangingFrontendContract(): void
    {
        $definition = $this->usersDefinition();
        $withInput = $definition->withRowInput(new RegistryRowInputDefinition(
            'departmentTitle',
            ColumnSelectDefinition::sqlExpression('departmentTitle', 'p.department_title')
        ));

        Assert::same($definition->rowInputs(), []);
        Assert::same(\array_map(
            static fn (RegistryRowInputDefinition $input): string => $input->key(),
            $withInput->rowInputs()
        ), ['departmentTitle']);
        Assert::same($withInput->jsonSerialize(), $definition->jsonSerialize());
    }

    public function rejectsInputWithoutSelectForSqlSource(): void
    {
        Expect::exception(InvalidRegistryDefinitionException::class);

        $this->usersDefinition()->withRowInput(new RegistryRowInputDefinition('departmentTitle'));
    }

    public function rejectsSqlSelectForExternalInput(): void
    {
        Expect::exception(InvalidRegistryDefinitionException::class);

        (new TableRegistryDefinition(
            registryKey: 'history',
            title: 'История',
            source: RegistrySourceDefinition::external('object_history'),
            columns: [new PrimaryKeyColumnRegistryDefinition('id')]
        ))->withRowInput(new RegistryRowInputDefinition(
            'historyType',
            ColumnSelectDefinition::sqlExpression('historyType', 'h.type')
        ));
    }

    public function rejectsInputThatCollidesWithColumnOrAuxiliarySelect(): void
    {
        Expect::exception(InvalidRegistryDefinitionException::class);

        (new TableRegistryDefinition(
            registryKey: 'users',
            title: 'Пользователи',
            source: RegistrySourceDefinition::from('public.users u'),
            columns: [
                (new PrimaryKeyColumnRegistryDefinition('id'))
                    ->withAuxiliarySelect(ColumnSelectDefinition::sqlExpression('departmentTitle', 'd.title')),
            ]
        ))->withRowInput(new RegistryRowInputDefinition(
            'departmentTitle',
            ColumnSelectDefinition::sqlExpression('departmentTitle', 'd.name')
        ));
    }

    /** @return array<array-key, mixed> */
    private static function arrayValue(mixed $value): array
    {
        if (!\is_array($value)) {
            throw new \UnexpectedValueException('Expected an array value.');
        }

        return $value;
    }
}
