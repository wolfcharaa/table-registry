<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Sql\PostgresRegistrySqlCompiler;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlCondition;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlQueryInput;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlRuntimeContext;

final class PostgresRegistrySqlCompilerTest extends RegistryTestCase
{
    public function testCompilesRowsQueryWithSearchFilterSortAndPagination(): void
    {
        $compiled = (new PostgresRegistrySqlCompiler())->compileRows($this->usersDefinition(), [
            'search' => 'иван',
            'filter_fullName' => 'петров',
            'filter_status' => ['active', 'blocked'],
            RegistrySqlQueryInput::SORT_COLUMN_KEY => 'fullName',
            RegistrySqlQueryInput::SORT_DIRECTION_KEY => 'desc',
            'page' => '2',
            'per_page' => '10',
        ]);

        $sql = $compiled->sql();
        self::assertStringContainsString('u.id AS "id"', $sql);
        self::assertStringContainsString("concat_ws(' ', p.last_name, p.first_name) AS \"fullName\"", $sql);
        self::assertStringContainsString('FROM public.users u', $sql);
        self::assertStringContainsString('LEFT JOIN public.user_profile p ON p.user_id = u.id', $sql);
        self::assertStringContainsString("CAST(concat_ws(' ', p.last_name, p.first_name) AS text) ILIKE :p0", $sql);
        self::assertStringContainsString("CAST(concat_ws(' ', p.last_name, p.first_name) AS text) ILIKE :p1", $sql);
        self::assertStringContainsString('u.status IN (:p2, :p3)', $sql);
        self::assertStringContainsString('ORDER BY p.last_name, p.first_name DESC', $sql);
        self::assertStringContainsString('LIMIT 10 OFFSET 10', $sql);

        self::assertSame([
            'p0' => '%иван%',
            'p1' => '%петров%',
            'p2' => 'active',
            'p3' => 'blocked',
        ], $compiled->params());
    }

    public function testUsesDefaultSortWhenRequestedColumnIsNotSortable(): void
    {
        $compiled = (new PostgresRegistrySqlCompiler())->compileRows($this->usersDefinition(), [
            RegistrySqlQueryInput::SORT_COLUMN_KEY => 'status',
            RegistrySqlQueryInput::SORT_DIRECTION_KEY => 'asc',
        ]);

        self::assertStringContainsString('ORDER BY u.id DESC', $compiled->sql());
    }

    public function testCompilesRuntimeConstraintCallback(): void
    {
        $definition = $this->usersDefinition()->withRuntimeConstraint(
            static function (
                RegistrySqlRuntimeContext $context,
                TableRegistryDefinition $definition,
                RegistrySqlQueryInput $query
            ): RegistrySqlCondition {
                $param = $context->param(42);

                return RegistrySqlCondition::raw('u.department_id = :' . $param);
            }
        );

        $compiled = (new PostgresRegistrySqlCompiler())->compileRows($definition);

        self::assertStringContainsString('(u.department_id = :p0)', $compiled->sql());
        self::assertSame(['p0' => 42], $compiled->params());
    }

    public function testCompilesExportRowsWithoutPagination(): void
    {
        $compiled = (new PostgresRegistrySqlCompiler())->compileExportRows($this->usersDefinition(), [
            'filter_status' => 'active',
        ]);

        self::assertStringNotContainsString('LIMIT', $compiled->sql());
        self::assertStringNotContainsString('OFFSET', $compiled->sql());
        self::assertStringContainsString('u.status = :p0', $compiled->sql());
        self::assertSame(['p0' => 'active'], $compiled->params());
    }
}

