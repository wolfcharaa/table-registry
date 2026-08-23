<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\ColumnSelectDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Filter\DateRangeFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Sql\PostgresRegistrySqlCompiler;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlCondition;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlQueryInput;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlRuntimeContext;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;

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

    public function testCompileCountUsesCountSourceWhenDefined(): void
    {
        $definition = new TableRegistryDefinition(
            registryKey: 'users',
            title: 'Пользователи',
            source: RegistrySourceDefinition::from('public.users_view u')
                ->withCountSource('public.users u'),
            columns: $this->usersDefinition()->columns()->all()
        );

        $compiler = new PostgresRegistrySqlCompiler();

        $rows = $compiler->compileRows($definition);
        $count = $compiler->compileCount($definition);

        self::assertStringContainsString('FROM public.users_view u', $rows->sql());
        self::assertStringContainsString('FROM public.users u', $count->sql());
        self::assertStringNotContainsString('FROM public.users_view u', $count->sql());
    }

    public function testCompileCountIgnoresOpaqueCountSourceKey(): void
    {
        $definition = new TableRegistryDefinition(
            registryKey: 'packages',
            title: 'Пакеты',
            source: RegistrySourceDefinition::from('(SELECT * FROM public.fis_export_package WHERE deleted_at IS NULL) p')
                ->withCountSource('fis_export_package:active'),
            columns: [
                (new PrimaryKeyColumnRegistryDefinition('id'))
                    ->withSelect(ColumnSelectDefinition::sqlExpression('id', 'p.id')),
                (new ColumnRegistryDefinition('createdAt', 'Дата создания'))
                    ->withSelect(ColumnSelectDefinition::sqlExpression('createdAt', 'p.created_at'))
                    ->withFilter(DateRangeFilterRegistryDefinition::forColumn('createdAt', 'Дата создания')),
            ]
        );

        $count = (new PostgresRegistrySqlCompiler())->compileCount($definition, [
            'createdAt_from' => '2026-07-24',
            'createdAt_to' => '2026-08-23',
        ]);

        self::assertStringContainsString(
            'FROM (SELECT * FROM public.fis_export_package WHERE deleted_at IS NULL) p',
            $count->sql()
        );
        self::assertStringNotContainsString('fis_export_package:active', $count->sql());
        self::assertStringContainsString('WHERE p.created_at >= :p0', $count->sql());
        self::assertStringContainsString('AND p.created_at < :p1', $count->sql());
        self::assertSame([
            'p0' => '2026-07-24 00:00:00',
            'p1' => '2026-08-24 00:00:00',
        ], $count->params());
    }

    public function testDoesNotSelectOutputOnlyColumns(): void
    {
        $definition = new TableRegistryDefinition(
            registryKey: 'people',
            title: 'Люди',
            source: RegistrySourceDefinition::from('public.people p'),
            columns: [
                new PrimaryKeyColumnRegistryDefinition('id'),
                new ColumnRegistryDefinition('firstName', 'Имя'),
                (new ColumnRegistryDefinition('displayName', 'ФИО'))
                    ->withOutputOnly(),
            ]
        );

        $compiled = (new PostgresRegistrySqlCompiler())->compileRows($definition);

        self::assertStringContainsString('"id" AS "id"', $compiled->sql());
        self::assertStringContainsString('"firstName" AS "firstName"', $compiled->sql());
        self::assertStringNotContainsString('"displayName" AS "displayName"', $compiled->sql());
    }
}
