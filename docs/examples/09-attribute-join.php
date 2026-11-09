<?php

declare(strict_types=1);

use Wolfcharaa\TableRegistry\Attribute\Column;
use Wolfcharaa\TableRegistry\Attribute\Field;
use Wolfcharaa\TableRegistry\Attribute\FieldMatch;
use Wolfcharaa\TableRegistry\Attribute\From;
use Wolfcharaa\TableRegistry\Attribute\Join;
use Wolfcharaa\TableRegistry\Attribute\JoinType;
use Wolfcharaa\TableRegistry\Attribute\PrimaryKey;
use Wolfcharaa\TableRegistry\Attribute\Project;
use Wolfcharaa\TableRegistry\Attribute\Relation;
use Wolfcharaa\TableRegistry\Attribute\Table;
use Wolfcharaa\TableRegistry\Compiler\AttributeTableRegistryCompiler;
use Wolfcharaa\TableRegistry\Sql\PostgresRegistrySqlCompiler;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

#[Relation('public.join_users')]
final class JoinExampleUser
{
    #[Field('user_id')]
    protected int $id;

    #[Field('department_id')]
    protected int $departmentId;
}

#[Relation('public.join_departments')]
final class JoinExampleDepartment
{
    #[Field('department_id')]
    protected int $id;

    #[Field]
    protected string $title;
}

#[Table('joined_users', 'Пользователи и подразделения')]
#[From(JoinExampleUser::class, 'user')]
#[Join(
    model: JoinExampleDepartment::class,
    alias: 'department',
    type: JoinType::Left,
    position: 10,
    on: [new FieldMatch('user.departmentId', 'department.id')]
)]
final class JoinExampleUsersTable
{
    #[Column('ID', 10)]
    #[Project('user')]
    #[PrimaryKey]
    protected int $id;

    #[Column('Подразделение', 20)]
    #[Project('department', 'title')]
    protected string $departmentTitle;
}

$definition = (new AttributeTableRegistryCompiler())->compile(JoinExampleUsersTable::class);
$sql = (new PostgresRegistrySqlCompiler())->compileRows($definition)->sql();

if (!str_contains($sql, 'LEFT JOIN "public"."join_departments" AS "department"')) {
    throw new RuntimeException('Expected Attribute JOIN was not compiled.');
}

echo $sql . PHP_EOL;
