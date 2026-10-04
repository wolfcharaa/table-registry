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
    private const BORDER_NAME_CLASS = 'OpenSpout\\Common\\Entity\\Style\\BorderName';
    private const BORDER_STYLE_CLASS = 'OpenSpout\\Common\\Entity\\Style\\BorderStyle';
    private const BORDER_WIDTH_CLASS = 'OpenSpout\\Common\\Entity\\Style\\BorderWidth';

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

        $temporaryPath = \tempnam(\sys_get_temp_dir(), 'xlsx_');
        if ($temporaryPath === false) {
            throw new \RuntimeException('Cannot allocate a temporary XLSX path.');
        }

        $filePath = $temporaryPath . '.xlsx';
        if (!\unlink($temporaryPath)) {
            throw new \RuntimeException('Cannot prepare a temporary XLSX path.');
        }

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
            $values[] = $this->stringifyCellValue($value);
        }

        $writer->addRow($this->rowWithStyle($values, $this->bodyStyle()));
    }

    /**
     * @param list<ColumnRegistryDefinition> $columns
     */
    private function headerRow(array $columns): Row
    {
        return $this->rowWithStyle(
            \array_map(static fn (ColumnRegistryDefinition $column): string => $column->title(), $columns),
            $this->headerStyle(),
            30
        );
    }

    private function headerStyle(): Style
    {
        if ((new \ReflectionClass(Style::class))->hasMethod('withFontBold')) {
            $style = $this->invokeStyle(new Style(), 'withFontBold', true);
            $style = $this->invokeStyle($style, 'withFontColor', Color::WHITE);
            $style = $this->invokeStyle($style, 'withBackgroundColor', self::HEADER_BG);
            $style = $this->invokeStyle(
                $style,
                'withCellAlignment',
                $this->classConstant(CellAlignment::class, 'CENTER')
            );
            $style = $this->invokeStyle($style, 'withShouldWrapText', true);

            return $this->invokeStyle(
                $style,
                'withBorder',
                $this->borderV5(self::BORDER_COLOR, 'MEDIUM')
            );
        }

        $style = new Style();
        $style = $this->invokeStyle($style, 'setFontBold');
        $style = $this->invokeStyle($style, 'setFontColor', Color::WHITE);
        $style = $this->invokeStyle($style, 'setBackgroundColor', self::HEADER_BG);
        $style = $this->invokeStyle(
            $style,
            'setCellAlignment',
            $this->classConstant(CellAlignment::class, 'CENTER')
        );
        $style = $this->invokeStyle($style, 'setShouldWrapText', true);

        return $this->invokeStyle(
            $style,
            'setBorder',
            $this->borderV4(self::BORDER_COLOR, 'WIDTH_MEDIUM')
        );
    }

    private function bodyStyle(): Style
    {
        if ((new \ReflectionClass(Style::class))->hasMethod('withShouldWrapText')) {
            $style = $this->invokeStyle(new Style(), 'withShouldWrapText', true);

            return $this->invokeStyle(
                $style,
                'withBorder',
                $this->borderV5(self::BODY_BORDER_COLOR, 'THIN')
            );
        }

        $style = $this->invokeStyle(new Style(), 'setShouldWrapText', true);

        return $this->invokeStyle(
            $style,
            'setBorder',
            $this->borderV4(self::BODY_BORDER_COLOR, 'WIDTH_THIN')
        );
    }

    /**
     * @param list<string> $values
     */
    private function rowWithStyle(array $values, Style $style, ?float $height = null): Row
    {
        if ((new \ReflectionClass(Row::class))->hasMethod('fromValuesWithStyle')) {
            $arguments = [$values, $style];
            if ($height !== null) {
                $arguments[] = $height;
            }

            $row = (new \ReflectionMethod(Row::class, 'fromValuesWithStyle'))->invoke(null, ...$arguments);
            if (!$row instanceof Row) {
                throw new \LogicException('OpenSpout row factory returned an invalid value.');
            }

            return $row;
        }

        $row = (new \ReflectionMethod(Row::class, 'fromValues'))->invoke(null, $values, $style);
        if (!$row instanceof Row) {
            throw new \LogicException('OpenSpout row factory returned an invalid value.');
        }
        if ($height !== null) {
            (new \ReflectionMethod($row, 'setHeight'))->invoke($row, $height);
        }

        return $row;
    }

    /** @param non-empty-string $color */
    private function borderV5(string $color, string $widthName): Border
    {
        $parts = [];
        foreach (['LEFT', 'RIGHT', 'TOP', 'BOTTOM'] as $name) {
            $part = (new \ReflectionClass(BorderPart::class))->newInstance(
                $this->classConstant(self::BORDER_NAME_CLASS, $name),
                $color,
                $this->classConstant(self::BORDER_WIDTH_CLASS, $widthName),
                $this->classConstant(self::BORDER_STYLE_CLASS, 'SOLID')
            );
            $parts[] = $part;
        }

        return (new \ReflectionClass(Border::class))->newInstance(...$parts);
    }

    /** @param non-empty-string $color */
    private function borderV4(string $color, string $widthName): Border
    {
        return new Border(
            $this->borderPartV4('LEFT', $color, $widthName),
            $this->borderPartV4('RIGHT', $color, $widthName),
            $this->borderPartV4('TOP', $color, $widthName),
            $this->borderPartV4('BOTTOM', $color, $widthName)
        );
    }

    /** @param non-empty-string $color */
    private function borderPartV4(string $name, string $color, string $widthName): BorderPart
    {
        $part = (new \ReflectionClass(BorderPart::class))->newInstance(
            $this->classConstant(Border::class, $name),
            $color,
            $this->classConstant(Border::class, $widthName),
            $this->classConstant(Border::class, 'STYLE_SOLID')
        );
        return $part;
    }

    private function invokeStyle(Style $style, string $method, mixed ...$arguments): Style
    {
        $result = (new \ReflectionMethod($style, $method))->invoke($style, ...$arguments);
        if (!$result instanceof Style) {
            throw new \LogicException(sprintf('OpenSpout style method %s returned an invalid value.', $method));
        }

        return $result;
    }

    private function classConstant(string $className, string $name): mixed
    {
        $constant = $className . '::' . $name;
        if (!\defined($constant)) {
            throw new \LogicException(\sprintf('OpenSpout constant %s is not available.', $constant));
        }

        return \constant($constant);
    }

    private function stringifyCellValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string)$value;
        }

        throw new \InvalidArgumentException(sprintf(
            'XLSX cell value must be scalar, null or Stringable; %s given.',
            get_debug_type($value)
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
