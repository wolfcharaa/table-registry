<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Select\Definition\SelectCriterionDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectFieldDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectOrderDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectPaginationDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectSearchDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectSortDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectSourceDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectValueType;
use Wolfcharaa\TableRegistry\Select\Query\SelectQueryInput;
use Wolfcharaa\TableRegistry\Select\Query\SelectQueryValidator;

#[Test]
final class SelectQueryValidatorTest
{
    public function validatesCanonicalInputWithoutTransportCoercion(): void
    {
        $definition = SelectDefinition::from(
            SelectSourceDefinition::relation('organization'),
            [SelectFieldDefinition::column('id')]
        )
            ->withCriterion(SelectCriterionDefinition::equal('departmentId', 'department_id', SelectValueType::Integer))
            ->withSearch(SelectSearchDefinition::contains(['name'], minLength: 2, maxLength: 20))
            ->withSort(SelectSortDefinition::ascending('name', 'name'))
            ->withOrderBy(SelectOrderDefinition::ascending('name'))
            ->withPagination(SelectPaginationDefinition::limit(10, 50));

        $result = (new SelectQueryValidator())->validate($definition, SelectQueryInput::from(
            search: '',
            criteria: ['unknown' => 1, 'departmentId' => '12'],
            sortKey: 'missing',
            limit: 51,
            offset: -1
        ));

        Assert::false($result->isValid());
        Assert::same(\array_map(
            static fn ($issue): string => $issue->code(),
            $result->issues()
        ), [
            'unknown_criterion',
            'invalid_criterion_value',
            'search_too_short',
            'unknown_sort',
            'limit_exceeds_max',
            'invalid_offset',
        ]);
    }

    public function acceptsStrictCanonicalValues(): void
    {
        $definition = SelectDefinition::from(
            SelectSourceDefinition::relation('organization'),
            [SelectFieldDefinition::column('id')]
        )->withCriterion(
            SelectCriterionDefinition::oneOf('ids', 'id', SelectValueType::Integer)
        );

        $result = (new SelectQueryValidator())->validate(
            $definition,
            SelectQueryInput::from(criteria: ['ids' => [1, 2]])
        );

        Assert::true($result->isValid());
    }
}
