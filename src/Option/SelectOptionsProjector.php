<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Option;

use Wolfcharaa\TableRegistry\Exception\OptionProjectionException;
use Wolfcharaa\TableRegistry\Select\Definition\SelectValueType;
use Wolfcharaa\TableRegistry\Select\Row\SelectRow;

/**
 * @api
 */
final class SelectOptionsProjector
{
    /**
     * @param array<array-key, mixed> $rows
     * @return list<SelectOption>
     */
    public function project(OptionListDefinition $definition, array $rows): array
    {
        $options = [];
        foreach ($rows as $rowIndex => $row) {
            if (!$row instanceof SelectRow) {
                throw OptionProjectionException::because(\sprintf(
                    'Option row %d must be an instance of %s.',
                    $rowIndex,
                    SelectRow::class
                ));
            }

            $value = $this->convert(
                $this->required($row, $definition->valueAlias(), $rowIndex),
                $definition->valueType(),
                false,
                $definition->valueAlias(),
                $rowIndex
            );
            if (!\is_string($value) && !\is_int($value)) {
                throw OptionProjectionException::because('Option value projection produced an invalid type.');
            }

            $label = $this->convert(
                $this->required($row, $definition->labelAlias(), $rowIndex),
                SelectValueType::String,
                false,
                $definition->labelAlias(),
                $rowIndex
            );
            if (!\is_string($label)) {
                throw OptionProjectionException::because('Option label projection produced an invalid type.');
            }

            $option = SelectOption::from($value, $label);

            if ($definition->codeAlias() !== null) {
                $code = $this->convert(
                    $this->required($row, $definition->codeAlias(), $rowIndex),
                    SelectValueType::String,
                    true,
                    $definition->codeAlias(),
                    $rowIndex
                );
                $option = $option->withCode($code === null ? null : (string)$code);
            }

            if ($definition->descriptionAlias() !== null) {
                $description = $this->convert(
                    $this->required($row, $definition->descriptionAlias(), $rowIndex),
                    SelectValueType::String,
                    true,
                    $definition->descriptionAlias(),
                    $rowIndex
                );
                $option = $option->withDescription($description === null ? null : (string)$description);
            }

            if ($definition->meta() !== []) {
                $meta = [];
                foreach ($definition->meta() as $key => $mapping) {
                    $converted = $this->convert(
                        $this->required($row, $mapping['alias'], $rowIndex),
                        $mapping['type'],
                        true,
                        $mapping['alias'],
                        $rowIndex
                    );
                    $meta[$key] = $converted;
                }
                $option = $option->withMeta($meta);
            }

            $options[] = $option;
        }

        return $options;
    }

    private function required(SelectRow $row, string $alias, int $rowIndex): mixed
    {
        if (!$row->has($alias)) {
            throw OptionProjectionException::because(\sprintf(
                'Option row %d does not contain mapped alias "%s".',
                $rowIndex,
                $alias
            ));
        }

        return $row->get($alias);
    }

    private function convert(
        mixed $value,
        SelectValueType $type,
        bool $nullable,
        string $alias,
        int $rowIndex
    ): string|int|float|bool|null {
        if ($value === null) {
            if ($nullable) {
                return null;
            }

            throw $this->typeError($alias, $rowIndex, $type);
        }

        $converted = match ($type) {
            SelectValueType::String => $this->stringValue($value),
            SelectValueType::Integer => $this->integerValue($value),
            SelectValueType::Float => $this->floatValue($value),
            SelectValueType::Boolean => $this->booleanValue($value),
            default => null,
        };

        if ($converted === null) {
            throw $this->typeError($alias, $rowIndex, $type);
        }

        return $converted;
    }

    private function stringValue(mixed $value): ?string
    {
        if (\is_string($value) || \is_int($value) || \is_float($value)) {
            return (string)$value;
        }

        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        return $value instanceof \Stringable ? (string)$value : null;
    }

    private function integerValue(mixed $value): ?int
    {
        if (\is_int($value)) {
            return $value;
        }

        return \is_string($value) && \preg_match('/^-?\d+$/D', $value) === 1 ? (int)$value : null;
    }

    private function floatValue(mixed $value): ?float
    {
        if (\is_float($value) || \is_int($value)) {
            return (float)$value;
        }

        return \is_string($value) && \is_numeric($value) ? (float)$value : null;
    }

    private function booleanValue(mixed $value): ?bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        return match ($value) {
            1, '1', 't', 'true' => true,
            0, '0', 'f', 'false' => false,
            default => null,
        };
    }

    private function typeError(string $alias, int $rowIndex, SelectValueType $type): OptionProjectionException
    {
        return OptionProjectionException::because(\sprintf(
            'Option row %d alias "%s" cannot be converted to %s.',
            $rowIndex,
            $alias,
            $type->value
        ));
    }
}
