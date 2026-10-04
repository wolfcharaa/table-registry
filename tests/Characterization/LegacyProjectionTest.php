<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Characterization;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Row\RegistryRowsProjector;
use Wolfcharaa\TableRegistry\Row\RegistryRowsResult;
use Wolfcharaa\TableRegistry\Tests\Support\LegacyRegistryFixture;

#[Test]
final class LegacyProjectionTest
{
    public function preservesProjectedRows(): void
    {
        $rows = (new RegistryRowsProjector())->project(
            LegacyRegistryFixture::definition(),
            LegacyRegistryFixture::databaseRows()
        );

        Assert::same(LegacyRegistryFixture::json($rows), $this->fixture('projected-rows.json'));
    }

    public function preservesRowsResultEnvelope(): void
    {
        $definition = LegacyRegistryFixture::definition();
        $rows = (new RegistryRowsProjector())->project($definition, LegacyRegistryFixture::databaseRows());
        $result = new RegistryRowsResult($definition, LegacyRegistryFixture::query(), '123', $rows);

        Assert::same(LegacyRegistryFixture::json($result), $this->fixture('rows-result.json'));
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(__DIR__ . '/fixtures/' . $name);

        return $contents === false ? throw new \RuntimeException('Cannot read fixture ' . $name) : $contents;
    }
}
