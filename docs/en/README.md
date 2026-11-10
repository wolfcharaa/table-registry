# TableRegistry documentation

[Русский](../README.md)

The `main` documentation targets the `v0.7` line. English and Russian are reviewed editions; PHP symbols,
commands, and executable examples are shared.

## Recommended path

1. Run the [quick start](../../README.en.md#quick-start) for the first frontend contract without a database.
2. Use [Attribute authoring](attributes.md) for a new static table definition.
3. Follow the [table read-model](table-read-model.md) for the complete runtime pipeline.
4. Use the [standalone select guide](select-and-options.md) for options and small read models.
5. Verify symbols in the shared [public API allowlist](../public-api.md) and behavior in
   [executable examples](examples/README.md).

## By task

| Task | Document |
| --- | --- |
| Evaluate purpose, requirements, and boundaries | [README](../../README.en.md) |
| Describe a relation/JOIN aggregate with Attributes | [Attribute authoring](attributes.md) |
| Check exact Attribute semantics and errors | [Attribute reference](attribute-reference.md) |
| Wire a provider, resolver, or another extension port | [Extending TableRegistry](extensions.md) |
| Build a table endpoint | [Table read-model](table-read-model.md) |
| Build an options/list endpoint | [Standalone select and option lists](select-and-options.md) |
| Find a complete runnable scenario | [Examples](examples/README.md) |
| Check a symbol's compatibility promise | [Public API](../public-api.md) |
| Adopt the Attribute frontend | [Migration `v0.6.x` → `v0.7.0`](migration-v0.6-to-v0.7.md) |

## Host application boundary

TableRegistry describes read models, validates canonical input, compiles SQL/params, and projects already fetched
rows. The application parses transport input, selects and opens a connection, executes SQL, performs authorization,
owns transaction/response lifecycle, and removes temporary XLSX files after sending them.

Application startup, migrations, database provisioning, deployment, backup, and production credentials are outside
the library documentation.
