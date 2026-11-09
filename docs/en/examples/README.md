# Examples

[Русский](../../examples/README.md)

These shared executable examples cover the supported library paths:

- `00-quick-start.php` — minimal object-authored definition and observable frontend contract;
- `01-table-definition.php` — columns, payload mode, filters, search, and default sort;
- `02-postgres-sql-compiler.php` — rows/count/export PostgreSQL compilation;
- `03-row-projection.php` — `{primaryKey, values}` projection and labels;
- `04-runtime-constraint.php` — request-context SQL constraint;
- `05-xlsx-export.php` — export policy, label flattening, and XLSX output;
- `06-psr-container-invoker.php` — class-string runtime resolver from a PSR container;
- `07-standalone-option-list.php` — standalone select, runtime scope, and typed options;
- `08-attribute-table.php` — minimal relation/aggregate Attributes and compiled contract;
- `09-attribute-join.php` — reusable relation models and a structured `LEFT JOIN`;
- `10-attribute-variants.php` — default/archive family and a variant-only column;
- `11-attribute-external-rows.php` — external projection and expected SQL rejection.

The examples do not execute SQL. The host application's DB adapter executes generated SQL with bound params.

From a source checkout, run all examples with:

```bash
composer examples
```

Each file runs in a separate PHP process. The runner also verifies that the XLSX example creates a non-empty temporary
file and removes it afterward.
