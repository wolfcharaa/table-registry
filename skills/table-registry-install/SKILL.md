---
name: table-registry-install
description: "Install, upgrade, or verify romanfedorskij/table-registry in a PHP project. Use when adding the package, resolving its Composer or platform requirements, enabling its bundled agent skills, or preparing a source checkout. Do not use for designing table definitions or request/row pipelines."
---

# Install TableRegistry

Work in the Composer project that owns the application. In a monorepo, locate the relevant `composer.json` before
changing dependencies. Preserve the project's existing Composer workflow, repositories, platform config, and version
policy.

## Consumer Project

Before changing anything, inspect `composer.json`, `composer.lock`, and the installed package when present. Distinguish
these cases:

- The package is absent: add it with `composer require romanfedorskij/table-registry`.
- It is declared but `vendor/` is missing: use the project's normal `composer install`; do not add the requirement again.
- It is already installed: do not update it unless the user requested an update.
- An update was requested: update only `romanfedorskij/table-registry` and the dependencies Composer proves must move;
  do not run an unrestricted project-wide update.

The library requires PHP 8.1+, `ext-json`, and `ext-mbstring`. Composer also resolves compatible `psr/container` and
`openspout/openspout` versions. Do not bypass a platform failure with `--ignore-platform-reqs` or hand-edit
`composer.lock`; report the missing extension or conflicting constraint and fix the environment or declared
constraint deliberately.

After installation, verify the resolved package and platform rather than assuming the command succeeded:

```bash
composer show romanfedorskij/table-registry
composer check-platform-reqs
```

Use the namespace `Wolfcharaa\TableRegistry\`. Do not add framework, database, or HTTP dependencies on behalf of this
library: it is framework-agnostic, compiles PostgreSQL SQL, and leaves query execution and transport integration to the
consumer application.

## Bundled Agent Skills

When the user asks to install the library's AI-agent skills, prefer the Composer plugin in a compatible consumer
project:

```bash
composer require --dev llm/skills
composer skills:init --quick
composer skills:update romanfedorskij/table-registry
```

Composer may ask the user to allow the plugin. The consumer owns that trust decision and the generated `skills.json`.
If the plugin cannot be used, copy only the desired directories from
`vendor/romanfedorskij/table-registry/skills/` into the agent's configured skills root. Do not modify the copies under
`vendor/`; they will be replaced by Composer.

## Library Source Checkout

When preparing this repository itself, install the development dependencies and run its test script:

```bash
composer install
composer test
```

Do not publish, tag, or change the package version unless the user explicitly requests a release.
