<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Characterization;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Tests\Support\LegacyRegistryFixture;

#[Test]
final class LegacyDefinitionJsonTest
{
    public function preservesSerializedDefinition(): void
    {
        Assert::same(
            LegacyRegistryFixture::json(LegacyRegistryFixture::definition()),
            $this->fixture('table-definition.json')
        );
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(__DIR__ . '/fixtures/' . $name);

        return $contents === false ? throw new \RuntimeException('Cannot read fixture ' . $name) : $contents;
    }
}
