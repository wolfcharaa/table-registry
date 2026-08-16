<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Export;

final class RegistryXlsxExportResult
{
    public function __construct(
        private readonly string $filePath,
        private readonly string $fileName
    ) {
    }

    public function filePath(): string
    {
        return $this->filePath;
    }

    public function fileName(): string
    {
        return $this->fileName;
    }
}
