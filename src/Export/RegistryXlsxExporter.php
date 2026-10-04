<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Export;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Wolfcharaa\TableRegistry\Column\ColumnRegistryDefinition;
use Wolfcharaa\TableRegistry\Definition\TableRegistryDefinition;

/**
 * @api
 */
final class RegistryXlsxExporter
{
    private const DEFAULT_SHEET_NAME = 'Результат';
    private const MAX_SHEET_NAME_LENGTH = 31;
    private const HEADER_BG = '1F6E8C';
    private const BORDER_COLOR = '6E6E6E';
    private const BODY_BORDER_COLOR = '8E8E8E';

    /**
     * @param list<ColumnRegistryDefinition> $columns
     * @param iterable<array<string, mixed>> $rows
     */
    public function export(
        TableRegistryDefinition $definition,
        array $columns,
        iterable $rows,
        ?string $fileNamePrefix = null
    ): RegistryXlsxExportResult {
        if ($columns === []) {
            throw new \InvalidArgumentException('XLSX export columns must not be empty.');
        }

        $filePath = \tempnam(\sys_get_temp_dir(), 'xlsx_') . '.xlsx';
        $writer = new Writer();
        $writer->openToFile($filePath);
        $writer->getCurrentSheet()->setName(self::normalizeSheetName($definition->title()));

        try {
            $this->writeHeader($writer, $columns);

            foreach ($rows as $row) {
                $this->writeRow($writer, $columns, $row);
            }

            $writer->close();
        } catch (\Throwable $exception) {
            try {
                $writer->close();
            } catch (\Throwable $ignored) {
            }

            if (\file_exists($filePath)) {
                \unlink($filePath);
            }

            throw $exception;
        }

        return new RegistryXlsxExportResult(
            $filePath,
            $this->fileName($fileNamePrefix ?? $definition->registryKey())
        );
    }

    public static function normalizeSheetName(string $sheetName): string
    {
        $normalized = \preg_replace('/[\\\\\/\?\*\[\]:]+/u', ' ', $sheetName);
        if ($normalized === null) {
            return self::DEFAULT_SHEET_NAME;
        }

        $normalized = \preg_replace('/\s+/u', ' ', $normalized);
        if ($normalized === null) {
            return self::DEFAULT_SHEET_NAME;
        }

        $normalized = \trim($normalized, " \t\n\r\0\x0B'");
        if ($normalized === '') {
            return self::DEFAULT_SHEET_NAME;
        }

        $normalized = \mb_substr($normalized, 0, self::MAX_SHEET_NAME_LENGTH, 'UTF-8');
        $normalized = \trim($normalized, " \t\n\r\0\x0B'");

        return $normalized !== '' ? $normalized : self::DEFAULT_SHEET_NAME;
    }

    /**
     * @param list<ColumnRegistryDefinition> $columns
     */
    private function writeHeader(Writer $writer, array $columns): void
    {
        foreach ($columns as $index => $column) {
            $headerLen = \mb_strlen($column->title());
            $width = (float)\max(12, \min(60, $headerLen + 4));
            $writer->getCurrentSheet()->setColumnWidth($width, $index + 1);
        }

        $writer->addRow($this->headerRow($columns));
    }

    /**
     * @param list<ColumnRegistryDefinition> $columns
     * @param array<string, mixed> $row
     */
    private function writeRow(Writer $writer, array $columns, array $row): void
    {
        $values = [];
        foreach ($columns as $column) {
            $value = $row[$column->key()] ?? null;
            $values[] = $value === null ? '' : (string)$value;
        }

        $writer->addRow(Row::fromValues($values, $this->bodyStyle()));
    }

    /**
     * @param list<ColumnRegistryDefinition> $columns
     */
    private function headerRow(array $columns): Row
    {
        $row = Row::fromValues(
            \array_map(static fn (ColumnRegistryDefinition $column): string => $column->title(), $columns),
            $this->headerStyle()
        );
        $row->setHeight(30);

        return $row;
    }

    private function headerStyle(): Style
    {
        return (new Style())
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(self::HEADER_BG)
            ->setCellAlignment(CellAlignment::CENTER)
            ->setShouldWrapText(true)
            ->setBorder(new Border(
                new BorderPart(Border::LEFT, self::BORDER_COLOR, Border::WIDTH_MEDIUM, Border::STYLE_SOLID),
                new BorderPart(Border::RIGHT, self::BORDER_COLOR, Border::WIDTH_MEDIUM, Border::STYLE_SOLID),
                new BorderPart(Border::TOP, self::BORDER_COLOR, Border::WIDTH_MEDIUM, Border::STYLE_SOLID),
                new BorderPart(Border::BOTTOM, self::BORDER_COLOR, Border::WIDTH_MEDIUM, Border::STYLE_SOLID)
            ));
    }

    private function bodyStyle(): Style
    {
        return (new Style())
            ->setShouldWrapText(true)
            ->setBorder(new Border(
                new BorderPart(Border::LEFT, self::BODY_BORDER_COLOR, Border::WIDTH_THIN, Border::STYLE_SOLID),
                new BorderPart(Border::RIGHT, self::BODY_BORDER_COLOR, Border::WIDTH_THIN, Border::STYLE_SOLID),
                new BorderPart(Border::TOP, self::BODY_BORDER_COLOR, Border::WIDTH_THIN, Border::STYLE_SOLID),
                new BorderPart(Border::BOTTOM, self::BODY_BORDER_COLOR, Border::WIDTH_THIN, Border::STYLE_SOLID)
            ));
    }

    private function fileName(string $prefix): string
    {
        $safePrefix = \preg_replace('/[^а-яёА-ЯЁa-zA-Z0-9_\-]/u', '_', $prefix);
        if (!\is_string($safePrefix) || \trim($safePrefix, '_') === '') {
            $safePrefix = 'registry_export';
        }

        return $safePrefix . '_' . \date('Ymd_His') . '.xlsx';
    }
}
