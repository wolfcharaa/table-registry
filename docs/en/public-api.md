# Public API

[Русский](../public-api.md)

This document explains the supported `v0.7.x` public API surface. A PHP file under `src/` and a Composer-autoloadable
symbol do not by themselves create a compatibility promise.

The exact language-neutral allowlist is maintained in the shared
[supported-symbols section](../public-api.md#supported-symbols). A contract test reads that list and compares native
public/protected signatures with the reviewed snapshot.

## Compatibility policy

- Symbols in the shared allowlist are supported throughout the `v0.7.x` line.
- Changing their public/protected native signatures requires explicit API review and a signature-snapshot update.
- Symbols marked `@internal` may change without a compatibility promise and must not be used by applications.
- Every new symbol under `src/` must either be allowlisted and marked `@api` or be marked `@internal`.
- Public/protected signatures must not reference internal types.
- The status and migration direction of existing API families are recorded in the
  [`v0.6.0` compatibility matrix](v0.6-bc-matrix.md).

## Extension ports

The supported application extension points are:

- `ColumnValueHydratorInterface`;
- `ColumnValueLabelResolverInterface`;
- `CompileTimeProviderResolverInterface`;
- `RelationMetadataProviderInterface`;
- `RelationSourceProviderInterface`;
- `SqlExpressionProviderInterface`;
- `RegistryFilterDefinitionInterface` and `RegistryFilterExpressionInterface`;
- `RegistryResolverInvokerInterface`;
- `RegistrySearchDefinitionInterface`;
- `RegistrySqlRuntimeConstraintInterface`;
- `ResolverInvokerInterface`;
- `SelectCompilerInterface`;
- `SelectRuntimeConstraintInterface`.

Add an interface only for a proven project extension case. Wanting to replace an immutable value object is not, by
itself, a reason to introduce an extension contract.

Purpose, composition, lifecycle, failures, and consumer examples for these ports are documented in
[`extensions.md`](extensions.md). The Attribute compiler, public Attributes, and validation limits are covered in
[`attributes.md`](attributes.md) and [`attribute-reference.md`](attribute-reference.md).
