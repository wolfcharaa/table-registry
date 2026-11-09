<?php

declare(strict_types=1);

use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$definition = new TableRegistryDefinition(
    registryKey: 'users',
    title: 'Users',
    source: RegistrySourceDefinition::from('public.users'),
    columns: [
        new PrimaryKeyColumnRegistryDefinition('id'),
        new ColumnRegistryDefinition('email', 'Email'),
    ]
);

$contract = $definition->jsonSerialize();

echo sprintf(
    "registry=%s primary=%s columns=%d\n",
    $contract['registryKey'],
    $contract['primaryKey'],
    count($contract['columns'])
);
