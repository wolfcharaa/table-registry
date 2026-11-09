<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\ColumnSelectDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Exception\UnsupportedRegistrySourceException;
use Wolfcharaa\TableRegistry\Filter\DateRangeFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Sql\PostgresRegistrySqlCompiler;
use Wolfcharaa\TableRegistry\Sql\CompiledRegistrySql;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlCondition;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlQueryInput;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlRuntimeContext;
use Wolfcharaa\TableRegistry\Row\RegistryRowInputDefinition;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;

#[Test]
final class PostgresRegistrySqlCompilerTest extends RegistryTestCase
{
    public function compilesRowsQueryWithSearchFilterSortAndPagination(): void
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
        Assert::string($sql)->contains('u.id AS "id"');
        Assert::string($sql)->contains("concat_ws(' ', p.last_name, p.first_name) AS \"fullName\"");
        Assert::string($sql)->contains('FROM public.users u');
        Assert::string($sql)->contains('LEFT JOIN public.user_profile p ON p.user_id = u.id');
        Assert::string($sql)->contains("CAST(concat_ws(' ', p.last_name, p.first_name) AS text) ILIKE :p0");
        Assert::string($sql)->contains("CAST(concat_ws(' ', p.last_name, p.first_name) AS text) ILIKE :p1");
        Assert::string($sql)->contains('u.status IN (:p2, :p3)');
        Assert::string($sql)->contains('ORDER BY p.last_name, p.first_name DESC');
        Assert::string($sql)->contains('LIMIT 10 OFFSET 10');

        Assert::same($compiled->params(), [
            'p0' => '%иван%',
            'p1' => '%петров%',
            'p2' => 'active',
            'p3' => 'blocked',
        ]);
    }

    public function usesDefaultSortWhenRequestedColumnIsNotSortable(): void
    {
        $compiled = (new PostgresRegistrySqlCompiler())->compileRows($this->usersDefinition(), [
            RegistrySqlQueryInput::SORT_COLUMN_KEY => 'status',
            RegistrySqlQueryInput::SORT_DIRECTION_KEY => 'asc',
        ]);

        Assert::string($compiled->sql())->contains('ORDER BY u.id DESC');
    }

    public function compilesRuntimeConstraintCallback(): void
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

        Assert::string($compiled->sql())->contains('(u.department_id = :p0)');
        Assert::same($compiled->params(), ['p0' => 42]);
    }

    public function compilesExportRowsWithoutPagination(): void
    {
        $compiled = (new PostgresRegistrySqlCompiler())->compileExportRows($this->usersDefinition(), [
            'filter_status' => 'active',
        ]);

        Assert::string($compiled->sql())->notContains('LIMIT');
        Assert::string($compiled->sql())->notContains('OFFSET');
        Assert::string($compiled->sql())->contains('u.status = :p0');
        Assert::same($compiled->params(), ['p0' => 'active']);
    }

    public function compileCountUsesCountSourceWhenDefined(): void
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

        Assert::string($rows->sql())->contains('FROM public.users_view u');
        Assert::string($count->sql())->contains('FROM public.users u');
        Assert::string($count->sql())->notContains('FROM public.users_view u');
    }

    public function compileCountIgnoresOpaqueCountSourceKey(): void
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

        Assert::string($count->sql())
            ->contains('FROM (SELECT * FROM public.fis_export_package WHERE deleted_at IS NULL) p');
        Assert::string($count->sql())->notContains('fis_export_package:active');
        Assert::string($count->sql())->contains('WHERE p.created_at >= :p0');
        Assert::string($count->sql())->contains('AND p.created_at < :p1');
        Assert::same($count->params(), [
            'p0' => '2026-07-24 00:00:00',
            'p1' => '2026-08-24 00:00:00',
        ]);
    }

    public function doesNotSelectOutputOnlyColumns(): void
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

        Assert::string($compiled->sql())->contains('"id" AS "id"');
        Assert::string($compiled->sql())->contains('"firstName" AS "firstName"');
        Assert::string($compiled->sql())->notContains('"displayName" AS "displayName"');
    }

    public function selectsInternalRowInputsWithoutTurningThemIntoColumns(): void
    {
        $definition = $this->usersDefinition()->withRowInput(new RegistryRowInputDefinition(
            'departmentTitle',
            ColumnSelectDefinition::sqlExpression('departmentTitle', 'p.department_title')
        ));

        $rows = (new PostgresRegistrySqlCompiler())->compileRows($definition);
        $export = (new PostgresRegistrySqlCompiler())->compileExportRows($definition);

        Assert::string($rows->sql())->contains('p.department_title AS "departmentTitle"');
        Assert::string($export->sql())->contains('p.department_title AS "departmentTitle"');
        Assert::same(
            \array_column($definition->jsonSerialize()['columns'], 'key'),
            ['id', 'fullName', 'status']
        );
    }

    public function rejectsExternalSourceForEverySqlCompilationMode(): void
    {
        $definition = new TableRegistryDefinition(
            registryKey: 'history',
            title: 'История',
            source: RegistrySourceDefinition::external('object_history'),
            columns: [new PrimaryKeyColumnRegistryDefinition('id')]
        );
        $compiler = new PostgresRegistrySqlCompiler();

        foreach ([
            static fn (): CompiledRegistrySql => $compiler->compileRows($definition),
            static fn (): CompiledRegistrySql => $compiler->compileCount($definition),
            static fn (): CompiledRegistrySql => $compiler->compileExportRows($definition),
        ] as $compile) {
            $error = null;

            try {
                $compile();
            } catch (\Throwable $caught) {
                $error = $caught;
            }

            Assert::true($error instanceof UnsupportedRegistrySourceException);
        }
    }

    public function keepsLegacyBindingsInsideEachFiberCall(): void
    {
        $compiler = new PostgresRegistrySqlCompiler();
        $definition = fn (int $areaId): TableRegistryDefinition => $this->usersDefinition()->withRuntimeConstraint(
            static function (RegistrySqlRuntimeContext $context) use ($areaId): RegistrySqlCondition {
                $parameter = $context->param($areaId);
                \Fiber::suspend();

                return RegistrySqlCondition::raw('u.area_id = :' . $parameter);
            }
        );

        $firstFiber = new \Fiber(fn () => $compiler->compileRows($definition(10)));
        $secondFiber = new \Fiber(fn () => $compiler->compileRows($definition(20)));
        $firstFiber->start();
        $secondFiber->start();
        $firstFiber->resume();
        $secondFiber->resume();

        $first = $firstFiber->getReturn();
        $second = $secondFiber->getReturn();
        if (!$first instanceof CompiledRegistrySql || !$second instanceof CompiledRegistrySql) {
            throw new \LogicException('Fiber compilation must return CompiledRegistrySql instances.');
        }

        Assert::same($first->params()['p0'], 10);
        Assert::same($second->params()['p0'], 20);
    }
}
