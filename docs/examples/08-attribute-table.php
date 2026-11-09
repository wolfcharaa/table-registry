<?php

declare(strict_types=1);

use Wolfcharaa\TableRegistry\Attribute\Column;
use Wolfcharaa\TableRegistry\Attribute\Field;
use Wolfcharaa\TableRegistry\Attribute\From;
use Wolfcharaa\TableRegistry\Attribute\PrimaryKey;
use Wolfcharaa\TableRegistry\Attribute\Project;
use Wolfcharaa\TableRegistry\Attribute\Relation;
use Wolfcharaa\TableRegistry\Attribute\Table;
use Wolfcharaa\TableRegistry\Compiler\AttributeTableRegistryCompiler;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

#[Relation('public.attribute_users')]
final class AttributeExampleUser
{
    #[Field('user_id')]
    protected int $id;

    #[Field('email_address')]
    protected string $email;
}

#[Table('attribute_users', 'Пользователи')]
#[From(AttributeExampleUser::class, 'user')]
final class AttributeExampleUsersTable
{
    #[Column('ID', 10)]
    #[Project('user')]
    #[PrimaryKey]
    protected int $id;

    #[Column('Email', 20)]
    #[Project('user')]
    protected string $email;
}

$definition = (new AttributeTableRegistryCompiler())->compile(AttributeExampleUsersTable::class);
$contract = $definition->jsonSerialize();

if ($contract['primaryKey'] !== 'id' || count($contract['columns']) !== 2) {
    throw new RuntimeException('Unexpected Attribute table contract.');
}

echo sprintf(
    "registry=%s primary=%s columns=%d\n",
    $contract['registryKey'],
    $contract['primaryKey'],
    count($contract['columns'])
);
