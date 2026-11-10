# TableRegistry

[Русский](README.md)

`TableRegistry` is a framework-agnostic PHP library for table and standalone read models, PostgreSQL query
compilation, stable frontend row payloads, and XLSX export.

Use it when the backend must explicitly define columns, filters, search, sorting, pagination, and response shape while
SQL execution, HTTP, authorization, and business actions remain in the host application. The `main` documentation
targets the `v0.7` line.

## Capabilities

- `TableRegistryDefinition` for tables with one primary key and a serializable frontend contract;
- an Attribute compiler for static relations, JOIN aggregates, and finite variants on ordinary PHP classes;
- `SelectDefinition` for options, lists, and small read models without table metadata;
- canonical query validation and PostgreSQL rows/count/export compilation without execution;
- raw-row projection into stable table and option payloads;
- runtime constraints for actor, tenant, and scope-dependent predicates;
- export-column policy and XLSX writing through OpenSpout.

The library does not open a database connection, read an HTTP request, authorize routes, define CRUD commands, or
own frontend layout/actions. Those responsibilities stay with the host application.

## Requirements and installation

- PHP 8.2+;
- `ext-json` and `ext-mbstring`;
- PostgreSQL for generated SQL;
- `psr/container` `^1.1 || ^2.0`;
- `openspout/openspout` `^4.28 || ^5.0` for XLSX.

Install the minor line from Packagist:

```bash
composer require romanfedorskij/table-registry:^0.7
```

Existing `v0.6.x` consumers should follow
[`docs/en/migration-v0.6-to-v0.7.md`](docs/en/migration-v0.6-to-v0.7.md).

## Quick start

Create `quick-start.php` beside `vendor/`:

```php
<?php

declare(strict_types=1);

use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Column\PrimaryKeyColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;
use Wolfcharaa\TableRegistry\Source\RegistrySourceDefinition;

require __DIR__ . '/vendor/autoload.php';

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
```

Run `php quick-start.php`. Expected output:

```text
registry=users primary=id columns=2
```

The maintained source is [`docs/examples/00-quick-start.php`](docs/examples/00-quick-start.php). For new static table
definitions in `v0.7`, use the Attribute path in
[`docs/examples/08-attribute-table.php`](docs/examples/08-attribute-table.php) and
[`docs/en/attributes.md`](docs/en/attributes.md). The object API remains supported for dynamic definitions.

## Integration model

```text
HTTP/CLI adapter → validate input → compile SQL + params → application executes SQL
                 → project raw rows → serialize response / export XLSX
```

The application owns transport parsing, DB adapters, authorization, and response lifecycle.

## Documentation

- [Documentation map](docs/en/README.md)
- [Attribute authoring guide](docs/en/attributes.md)
- [Attribute reference](docs/en/attribute-reference.md)
- [Extension ports](docs/en/extensions.md)
- [Table read-model](docs/en/table-read-model.md)
- [Standalone select and option lists](docs/en/select-and-options.md)
- [Executable examples](docs/en/examples/README.md)
- [`v0.6.x` → `v0.7.0` migration](docs/en/migration-v0.6-to-v0.7.md)
- [Public API allowlist](docs/public-api.md)

## AI agent skills

The package ships `table-registry-install` and `table-registry-use`. With the optional `llm/skills` Composer plugin:

```bash
composer require --dev llm/skills
composer skills:init --quick
composer skills:update romanfedorskij/table-registry
```

Composer asks separately before allowing the plugin. Without it, copy the desired directory from
`vendor/romanfedorskij/table-registry/skills/` to the agent's configured skills root; do not edit the vendor copy.

## Compatibility

The supported `v0.7.x` surface is listed in [`docs/public-api.md`](docs/public-api.md) and checked by a signature
snapshot. An autoloadable class under `src/` is not automatically a compatibility promise; consumers should depend on
allowlisted `@api` symbols only.

## Development

```bash
composer install
composer check
```

`composer check` validates Composer metadata, all test suites, PHPStan, and every executable example.

## License

[MIT](LICENSE)
