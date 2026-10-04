<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Export\RegistryExportColumnResolver;
use Wolfcharaa\TableRegistry\Export\RegistryXlsxExporter;

#[Test]
final class RegistryXlsxExporterTest extends RegistryTestCase
{
    public function writesReadableXlsxWithInstalledOpenSpoutVersion(): void
    {
        $definition = $this->usersDefinition();
        $columns = (new RegistryExportColumnResolver())->resolve($definition);
        $result = (new RegistryXlsxExporter())->export(
            $definition,
            $columns,
            [[
                'id' => 42,
                'fullName' => 'Петров Иван',
                'status' => 'active',
            ]],
            'people'
        );

        try {
            Assert::true(file_exists($result->filePath()));
            Assert::true(filesize($result->filePath()) > 0);

            $archive = new \ZipArchive();
            Assert::same($archive->open($result->filePath()), true);
            Assert::true($archive->locateName('xl/workbook.xml') !== false);
            $archive->close();
        } finally {
            if (file_exists($result->filePath())) {
                unlink($result->filePath());
            }
        }
    }
}
