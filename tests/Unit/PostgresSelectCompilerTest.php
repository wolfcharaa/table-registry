<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Select\Definition\SelectCountDefinition;
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
use Wolfcharaa\TableRegistry\Select\Query\SelectSortDirection;
use Wolfcharaa\TableRegistry\Select\Runtime\SelectRuntimeContext;
use Wolfcharaa\TableRegistry\Select\Sql\CompiledSelect;
use Wolfcharaa\TableRegistry\Select\Sql\PostgresSelectCompiler;
use Wolfcharaa\TableRegistry\Select\Sql\SelectCondition;

#[Test]
final class PostgresSelectCompilerTest
{
    public function compilesStandaloneSelectAndOptionalCountPlan(): void
    {
        $definition = $this->definition()->withRuntimeConstraint(
            static function (SelectRuntimeContext $context): SelectCondition {
                return SelectCondition::sql('o.area_id = ' . $context->param(77));
            }
        );

        $input = SelectQueryInput::from(
            search: 'A%_',
            criteria: ['departmentIds' => [2, 3]],
            sortKey: 'label',
            sortDirection: SelectSortDirection::Descending,
            limit: 20,
            offset: 40
        );

        $plan = (new PostgresSelectCompiler())->compileReadPlan($definition, $input);
        $rows = $plan->rows();

        Assert::string($rows->sql())->contains('"o"."id" AS "value"');
        Assert::string($rows->sql())->contains('FROM "public"."organization" AS "o"');
        Assert::string($rows->sql())->contains('LEFT JOIN "public"."department" AS "d" ON d.id = o.department_id');
        Assert::string($rows->sql())->contains('o.department_id IN (:p0, :p1)');
        Assert::string($rows->sql())->contains("CAST(o.name AS text) ILIKE :p2 ESCAPE E'\\\\'");
        Assert::string($rows->sql())->contains('(o.area_id = :p3)');
        Assert::string($rows->sql())->contains('ORDER BY o.name DESC, o.id ASC');
        Assert::string($rows->sql())->contains('LIMIT 20 OFFSET 40');
        Assert::same($rows->params(), [
            'p0' => 2,
            'p1' => 3,
            'p2' => '%A\\%\\_%',
            'p3' => 77,
        ]);

        $count = $plan->count();
        Assert::notNull($count);
        Assert::string($count->sql())->contains('SELECT COUNT(*)::bigint AS "total"');
        Assert::string($count->sql())->notContains('ORDER BY');
        Assert::string($count->sql())->notContains('LIMIT');
        Assert::same($count->params(), $rows->params());
    }

    public function compilerDoesNotRetainParameterStateBetweenCalls(): void
    {
        $compiler = new PostgresSelectCompiler();
        $definition = $this->definition();
        $input = SelectQueryInput::from(criteria: ['departmentIds' => [5]]);

        $first = $compiler->compile($definition, $input);
        $second = $compiler->compile($definition, $input);

        Assert::same($first->params(), ['p0' => 5]);
        Assert::same($second->params(), ['p0' => 5]);
        Assert::same($first->sql(), $second->sql());
    }

    public function compilerKeepsBindingsInsideEachFiberCall(): void
    {
        $compiler = new PostgresSelectCompiler();
        $firstFiber = new \Fiber(fn () => $compiler->compile($this->fiberDefinition(10)));
        $secondFiber = new \Fiber(fn () => $compiler->compile($this->fiberDefinition(20)));

        $firstFiber->start();
        $secondFiber->start();
        $firstFiber->resume();
        $secondFiber->resume();

        $first = $firstFiber->getReturn();
        $second = $secondFiber->getReturn();
        if (!$first instanceof CompiledSelect || !$second instanceof CompiledSelect) {
            throw new \LogicException('Fiber compilation must return CompiledSelect instances.');
        }

        Assert::same($first->params(), ['p0' => 10]);
        Assert::same($second->params(), ['p0' => 20]);
        Assert::string($first->sql())->contains('o.area_id = :p0');
        Assert::string($second->sql())->contains('o.area_id = :p0');
    }

    private function definition(): SelectDefinition
    {
        return SelectDefinition::from(
            SelectSourceDefinition::relation('public.organization', 'o')
                ->withLeftJoin('public.department', 'd.id = o.department_id', 'd'),
            [
                SelectFieldDefinition::column('o.id', 'value'),
                SelectFieldDefinition::column('o.name', 'label'),
                SelectFieldDefinition::column('d.code', 'code'),
            ]
        )
            ->withCriterion(SelectCriterionDefinition::oneOf(
                'departmentIds',
                'o.department_id',
                SelectValueType::Integer
            ))
            ->withSearch(SelectSearchDefinition::contains(['o.name']))
            ->withSort(SelectSortDefinition::both('label', 'o.name'))
            ->withOrderBy(SelectOrderDefinition::ascending('o.name'))
            ->withOrderBy(SelectOrderDefinition::ascending('o.id'))
            ->withPagination(SelectPaginationDefinition::limit(25, 100))
            ->withCount(SelectCountDefinition::matchingRows());
    }

    private function fiberDefinition(int $areaId): SelectDefinition
    {
        return SelectDefinition::from(
            SelectSourceDefinition::relation('organization', 'o'),
            [SelectFieldDefinition::column('o.id', 'id')]
        )->withRuntimeConstraint(
            static function (SelectRuntimeContext $context) use ($areaId): SelectCondition {
                $placeholder = $context->param($areaId);
                \Fiber::suspend();

                return SelectCondition::sql('o.area_id = ' . $placeholder);
            }
        );
    }
}
