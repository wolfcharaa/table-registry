<?php

declare(strict_types=1);

use Wolfcharaa\TableRegistry\Attribute\Column;
use Wolfcharaa\TableRegistry\Attribute\ExternalField;
use Wolfcharaa\TableRegistry\Attribute\ExternalRows;
use Wolfcharaa\TableRegistry\Attribute\PrimaryKey;
use Wolfcharaa\TableRegistry\Attribute\Table;
use Wolfcharaa\TableRegistry\Compiler\AttributeTableRegistryCompiler;
use Wolfcharaa\TableRegistry\Exception\UnsupportedRegistrySourceException;
use Wolfcharaa\TableRegistry\Row\RegistryRowsProjector;
use Wolfcharaa\TableRegistry\Sql\PostgresRegistrySqlCompiler;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

#[Table('external_history', 'История')]
#[ExternalRows('object_history')]
final class ExternalRowsExampleTable
{
    #[Column('ID', 10)]
    #[ExternalField('object_id')]
    #[PrimaryKey]
    protected int $id;

    #[Column('Действие', 20)]
    #[ExternalField('action_name')]
    protected string $action;
}

$definition = (new AttributeTableRegistryCompiler())->compile(ExternalRowsExampleTable::class);
$rows = (new RegistryRowsProjector())->project($definition, [[
    'object_id' => 15,
    'action_name' => 'created',
]]);

if ($rows[0]['primaryKey'] !== '15' || $rows[0]['values']['action']['value'] !== 'created') {
    throw new RuntimeException('Unexpected external row projection.');
}

try {
    (new PostgresRegistrySqlCompiler())->compileRows($definition);
} catch (UnsupportedRegistrySourceException) {
    echo "source=object_history primary=15 sql=unsupported\n";

    return;
}

throw new RuntimeException('External rows must not compile as PostgreSQL.');
