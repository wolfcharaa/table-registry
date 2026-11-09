<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit\Compiler;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\TableRegistry\Attribute\Column;
use Wolfcharaa\TableRegistry\Attribute\DefaultSort;
use Wolfcharaa\TableRegistry\Attribute\Field;
use Wolfcharaa\TableRegistry\Attribute\FieldMatch;
use Wolfcharaa\TableRegistry\Attribute\From;
use Wolfcharaa\TableRegistry\Attribute\Join;
use Wolfcharaa\TableRegistry\Attribute\JoinType;
use Wolfcharaa\TableRegistry\Attribute\Pagination;
use Wolfcharaa\TableRegistry\Attribute\PrimaryKey;
use Wolfcharaa\TableRegistry\Attribute\Project;
use Wolfcharaa\TableRegistry\Attribute\Relation;
use Wolfcharaa\TableRegistry\Attribute\Search;
use Wolfcharaa\TableRegistry\Attribute\Searchable;
use Wolfcharaa\TableRegistry\Attribute\Sort;
use Wolfcharaa\TableRegistry\Attribute\SqlExpression;
use Wolfcharaa\TableRegistry\Attribute\Table;
use Wolfcharaa\TableRegistry\Attribute\TextFilter;
use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\ColumnSelectDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Compiler\AttributeTableRegistryCompiler;
use Wolfcharaa\TableRegistry\Compiler\CompiledTableDefinitionCollection;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Exception\AttributeCompilationException;
use Wolfcharaa\TableRegistry\Export\RegistryExportColumnResolver;
use Wolfcharaa\TableRegistry\Filter\TextFilterRegistryDefinition;
use Wolfcharaa\TableRegistry\Query\RegistryPaginationDefinition;
use Wolfcharaa\TableRegistry\Row\RegistryRowsProjector;
use Wolfcharaa\TableRegistry\Search\SearchBySearchableColumnsDefinition;
use Wolfcharaa\TableRegistry\Sql\PostgresRegistrySqlCompiler;
use Wolfcharaa\TableRegistry\Sort\RegistryDefaultSortDefinition;
use Wolfcharaa\TableRegistry\Sort\RegistrySortDefinition;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;
use Wolfcharaa\TableRegistry\Value\IntegerValue;
use Wolfcharaa\TableRegistry\Value\StringValue;

#[Test]
final class AttributeTableRegistryCompilerTest
{
    public function compilesAttributeAggregateIntoExistingRuntimePipeline(): void
    {
        $definition = (new AttributeTableRegistryCompiler())->compile(CompilerUsersTable::class);
        $objectAuthored = $this->objectAuthoredDefinition();

        Assert::same($definition->registryKey(), 'compiler_users');
        Assert::same($definition->title(), 'Пользователи');
        Assert::same(
            \array_map(static fn ($column): string => $column->key(), $definition->columns()->all()),
            ['id', 'email', 'departmentTitle', 'rank']
        );
        Assert::same($definition->search()->queryKey(), 'q');
        Assert::same($definition->pagination()->defaultPerPage(), 20);
        Assert::same($definition->pagination()->maxPerPage(), 100);
        Assert::same($definition->defaultSort()?->columnKey(), 'email');
        Assert::same($definition->defaultSort()->direction(), 'desc');
        Assert::same($definition->jsonSerialize(), $objectAuthored->jsonSerialize());

        $query = [
            'q' => 'roman',
            'filter_email' => 'example.org',
            'page' => 2,
            'per_page' => 10,
        ];
        $sqlCompiler = new PostgresRegistrySqlCompiler();
        $compiled = $sqlCompiler->compileRows($definition, $query);
        $objectSql = $sqlCompiler->compileRows($objectAuthored, $query);

        Assert::string($compiled->sql())->contains('FROM "public"."users" AS "user"');
        Assert::string($compiled->sql())->contains(
            'LEFT JOIN "public"."departments" AS "department" ON "user"."department_id" = "department"."department_id"'
        );
        Assert::string($compiled->sql())->contains('"user"."user_id" AS "id"');
        Assert::string($compiled->sql())->contains('"department"."title" AS "departmentTitle"');
        Assert::string($compiled->sql())->contains('dense_rank() OVER (ORDER BY "user"."user_id") AS "rank"');
        Assert::string($compiled->sql())->contains('LIMIT 10 OFFSET 10');
        Assert::same($compiled->params(), [
            'p0' => '%roman%',
            'p1' => '%example.org%',
        ]);
        Assert::same($compiled->sql(), $objectSql->sql());
        Assert::same($compiled->params(), $objectSql->params());

        $rows = [[
            'id' => 7,
            'email' => 'roman@example.org',
            'departmentTitle' => 'Platform',
            'rank' => 1,
        ]];
        $projector = new RegistryRowsProjector();
        $projected = $projector->project($definition, $rows);
        Assert::same($projected[0]['primaryKey'], '7');
        Assert::same($projected[0]['values']['departmentTitle']['value'], 'Platform');
        Assert::same($projected, $projector->project($objectAuthored, $rows));

        $export = new RegistryExportColumnResolver();
        Assert::same(
            \array_map(static fn ($column): string => $column->key(), $export->resolve($definition)),
            \array_map(static fn ($column): string => $column->key(), $export->resolve($objectAuthored))
        );
    }

    public function compilesDefaultCatalogIntoImmutableCollection(): void
    {
        $definitions = (new AttributeTableRegistryCompiler())->compileAll([
            CompilerUsersTable::class,
        ]);

        Assert::same($definitions->count(), 1);
        Assert::same($definitions->require('compiler_users')->registryKey(), 'compiler_users');
        Assert::same($definitions->get('missing'), null);
        Assert::same(\array_keys($definitions->all()), ['compiler_users']);
    }

    public function reportsInvalidAggregateThroughPublicDiagnosticContract(): void
    {
        try {
            (new AttributeTableRegistryCompiler())->compile(CompilerMissingTableAggregate::class);
        } catch (AttributeCompilationException $exception) {
            Assert::same($exception->issues()[0]->code(), 'definition.invalid');
            Assert::same($exception->issues()[0]->consumer(), CompilerMissingTableAggregate::class);
            Assert::string($exception->issues()[0]->message())->contains('Table Attribute');

            return;
        }

        Expect::exception(AttributeCompilationException::class);
        throw new \LogicException('Compiler must reject an aggregate without Table.');
    }

    public function collectionRequiresKnownDefinition(): void
    {
        Expect::exception(\OutOfBoundsException::class);

        (new CompiledTableDefinitionCollection())->require('missing');
    }

    private function objectAuthoredDefinition(): TableRegistryDefinition
    {
        $id = (new PrimaryKeyColumnRegistryDefinition('id', 'ID'))
            ->withSelect(ColumnSelectDefinition::sqlExpression('id', '"user"."user_id"'))
            ->withRowTypeObject(IntegerValue::class);
        $email = (new ColumnRegistryDefinition('email', 'Email'))
            ->withSelect(ColumnSelectDefinition::sqlExpression('email', '"user"."email_address"'))
            ->withRowTypeObject(StringValue::class)
            ->withSearchable()
            ->withSort(RegistrySortDefinition::byColumn('email'))
            ->withFilter(TextFilterRegistryDefinition::contains('filter_email', 'email', 'Email'));
        $department = (new ColumnRegistryDefinition('departmentTitle', 'Подразделение'))
            ->withSelect(ColumnSelectDefinition::sqlExpression('departmentTitle', '"department"."title"'))
            ->withRowTypeObject(StringValue::class);
        $rank = (new ColumnRegistryDefinition('rank', 'Ранг'))
            ->withSelect(ColumnSelectDefinition::sqlExpression(
                'rank',
                'dense_rank() OVER (ORDER BY "user"."user_id")'
            ))
            ->withRowTypeObject(IntegerValue::class);

        return (new TableRegistryDefinition(
            'compiler_users',
            'Пользователи',
            RegistrySourceDefinition::from('"public"."users" AS "user"')
                ->withConnection('default')
                ->withJoin(
                    'LEFT JOIN "public"."departments" AS "department"'
                    . ' ON "user"."department_id" = "department"."department_id"'
                ),
            [$id, $email, $department, $rank]
        ))
            ->withSearch(new SearchBySearchableColumnsDefinition('q'))
            ->withPagination(new RegistryPaginationDefinition(defaultPerPage: 20, maxPerPage: 100))
            ->withDefaultSort(new RegistryDefaultSortDefinition('email', 'desc'));
    }
}

#[Relation('public.users')]
final class CompilerUserRelation
{
    #[Field('user_id')]
    protected int $id;

    #[Field('department_id')]
    protected int $departmentId;

    #[Field('email_address')]
    protected string $email;
}

#[Relation('public.departments')]
final class CompilerDepartmentRelation
{
    #[Field('department_id')]
    protected int $id;

    #[Field('title')]
    protected string $title;
}

#[Table('compiler_users', 'Пользователи')]
#[From(CompilerUserRelation::class, 'user')]
#[Join(
    model: CompilerDepartmentRelation::class,
    alias: 'department',
    type: JoinType::Left,
    position: 10,
    on: [new FieldMatch('user.departmentId', 'department.id')]
)]
#[Search('q')]
#[Pagination(defaultPerPage: 20, maxPerPage: 100)]
#[DefaultSort('email', 'desc')]
final class CompilerUsersTable
{
    #[Column('Подразделение', 30)]
    #[Project('department', 'title')]
    protected string $departmentTitle;

    #[Column('ID', 10)]
    #[Project('user')]
    #[PrimaryKey]
    protected int $id;

    #[Column('Email', 20)]
    #[Project('user')]
    #[Searchable]
    #[Sort]
    #[TextFilter('filter_email')]
    protected string $email;

    #[Column('Ранг', 40)]
    #[SqlExpression('dense_rank() OVER (ORDER BY "user"."user_id")')]
    protected int $rank;
}

#[From(CompilerUserRelation::class, 'user')]
final class CompilerMissingTableAggregate
{
    #[Column('ID', 10)]
    #[Project('user')]
    #[PrimaryKey]
    protected int $id;
}
