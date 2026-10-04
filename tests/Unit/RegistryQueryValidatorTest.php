<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Exception\InvalidRegistryQueryException;
use Wolfcharaa\TableRegistry\Filter\DateRangeFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\IntegerFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\TimeRangeFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Query\RegistryQueryValidator;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlQueryInput;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;

#[Test]
final class RegistryQueryValidatorTest extends RegistryTestCase
{
    public function acceptsValidRegistryQuery(): void
    {
        $result = (new RegistryQueryValidator())->validate($this->usersDefinition(), [
            'search' => 'иван',
            'filter_fullName' => 'петров',
            'filter_status' => ['active', 'blocked'],
            RegistrySqlQueryInput::SORT_COLUMN_KEY => 'fullName',
            RegistrySqlQueryInput::SORT_DIRECTION_KEY => 'desc',
            'page' => '2',
            'per_page' => '10',
            'selected_primary_keys' => ['1', '2'],
        ]);

        Assert::true($result->isValid());
        Assert::same($result->jsonSerialize(), ['valid' => true, 'issues' => []]);

        $result->assertValid();
    }

    public function reportsInvalidRegistryQuery(): void
    {
        $result = (new RegistryQueryValidator())->validate($this->validationDefinition(), [
            'unexpected' => 'value',
            RegistrySqlQueryInput::SORT_COLUMN_KEY => 'age',
            RegistrySqlQueryInput::SORT_DIRECTION_KEY => 'sideways',
            'page' => '0',
            'per_page' => '251',
            'filter_age' => 'old',
            'createdAt_from' => '2026-02-30',
            'scheduledAt_to' => '25:00',
        ]);

        Assert::false($result->isValid());
        Assert::same(\array_map(
            static fn ($issue): string => $issue->code(),
            $result->issues()
        ), [
            'unknown_query_key',
            'not_sortable_column',
            'invalid_sort_direction',
            'invalid_page',
            'per_page_exceeds_max',
            'invalid_filter_value',
            'invalid_date_filter_value',
            'invalid_time_filter_value',
        ]);
    }

    public function canAssertValidRegistryQuery(): void
    {
        $result = (new RegistryQueryValidator())->validate($this->usersDefinition(), [
            RegistrySqlQueryInput::SORT_COLUMN_KEY => 'missing',
        ]);

        Expect::exception(InvalidRegistryQueryException::class);

        $result->assertValid();
    }

    private function validationDefinition(): TableRegistryDefinition
    {
        return new TableRegistryDefinition(
            registryKey: 'validation',
            title: 'Validation',
            source: RegistrySourceDefinition::from('public.validation_rows'),
            columns: [
                new PrimaryKeyColumnRegistryDefinition('id'),
                (new ColumnRegistryDefinition('age', 'Возраст'))
                    ->withFilter(IntegerFilterRegistryDefinition::exact(
                        queryKey: 'filter_age',
                        columnKey: 'age',
                        title: 'Возраст'
                    )),
                (new ColumnRegistryDefinition('createdAt', 'Дата создания'))
                    ->withFilter(DateRangeFilterRegistryDefinition::forColumn('createdAt', 'Дата создания')),
                (new ColumnRegistryDefinition('scheduledAt', 'Время'))
                    ->withFilter(TimeRangeFilterRegistryDefinition::forColumn('scheduledAt', 'Время')),
            ]
        );
    }
}
