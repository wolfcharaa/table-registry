<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Characterization;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Sql\PostgresRegistrySqlCompiler;
use Wolfcharaa\TableRegistry\Tests\Support\LegacyRegistryFixture;

#[Test]
final class LegacySqlCompilationTest
{
    public function preservesRowsSqlAndParams(): void
    {
        $compiled = (new PostgresRegistrySqlCompiler())->compileRows(
            LegacyRegistryFixture::definition(),
            LegacyRegistryFixture::query()
        );

        Assert::same(rtrim($compiled->sql()) . PHP_EOL, $this->fixture('rows.sql'));
        Assert::same(LegacyRegistryFixture::json($compiled->params()), $this->fixture('rows.params.json'));
    }

    public function preservesCountSqlAndParams(): void
    {
        $compiled = (new PostgresRegistrySqlCompiler())->compileCount(
            LegacyRegistryFixture::definition(),
            LegacyRegistryFixture::query()
        );

        Assert::same(rtrim($compiled->sql()) . PHP_EOL, $this->fixture('count.sql'));
        Assert::same(LegacyRegistryFixture::json($compiled->params()), $this->fixture('count.params.json'));
    }

    public function preservesExportSqlAndParams(): void
    {
        $compiled = (new PostgresRegistrySqlCompiler())->compileExportRows(
            LegacyRegistryFixture::definition(),
            LegacyRegistryFixture::query()
        );

        Assert::same(rtrim($compiled->sql()) . PHP_EOL, $this->fixture('export.sql'));
        Assert::same(LegacyRegistryFixture::json($compiled->params()), $this->fixture('export.params.json'));
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(__DIR__ . '/fixtures/' . $name);

        return $contents === false ? throw new \RuntimeException('Cannot read fixture ' . $name) : $contents;
    }
}
