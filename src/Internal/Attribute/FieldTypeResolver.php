<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Internal\Attribute;

use Wolfcharaa\TableRegistry\Attribute\Field;
use Wolfcharaa\TableRegistry\Exception\InvalidRegistryDefinitionException;
use Wolfcharaa\TableRegistry\Value\BooleanValue;
use Wolfcharaa\TableRegistry\Value\DateTimeValue;
use Wolfcharaa\TableRegistry\Value\IntegerValue;
use Wolfcharaa\TableRegistry\Value\JsonListValue;
use Wolfcharaa\TableRegistry\Value\PrimitiveValueObjectInterface;
use Wolfcharaa\TableRegistry\Value\StringValue;

/**
 * @internal
 */
final class FieldTypeResolver
{
    /**
     * @return array{rowType: class-string<PrimitiveValueObjectInterface>, nullable: bool}
     */
    public function resolve(\ReflectionProperty $property, Field $field): array
    {
        $type = $property->getType();

        if ($field->rowType !== null) {
            $this->assertRowType($field->rowType, $property);

            if ($field->nullable === null && !$type instanceof \ReflectionNamedType) {
                throw $this->invalid($property, 'must declare nullable when its row type is explicit and PHP type is ambiguous');
            }

            return [
                'rowType' => $field->rowType,
                'nullable' => $field->nullable ?? $type->allowsNull(),
            ];
        }

        if (!$type instanceof \ReflectionNamedType || $type->getName() === 'mixed') {
            throw $this->invalid($property, 'requires an explicit row type for an untyped, mixed, union, or intersection property');
        }

        $rowType = $this->infer($type, $property);

        return [
            'rowType' => $rowType,
            'nullable' => $field->nullable ?? $type->allowsNull(),
        ];
    }

    /** @return class-string<PrimitiveValueObjectInterface> */
    private function infer(\ReflectionNamedType $type, \ReflectionProperty $property): string
    {
        $name = $type->getName();

        if ($type->isBuiltin()) {
            return match ($name) {
                'string' => StringValue::class,
                'int' => IntegerValue::class,
                'bool' => BooleanValue::class,
                'array' => JsonListValue::class,
                default => throw $this->invalid($property, \sprintf('has unsupported native type "%s"', $name)),
            };
        }

        if (\is_a($name, \DateTimeInterface::class, true)) {
            return DateTimeValue::class;
        }

        if (\enum_exists($name)) {
            $enum = new \ReflectionEnum($name);
            if ($enum->isBacked()) {
                $backingType = $enum->getBackingType()->getName();

                return $backingType === 'int' ? IntegerValue::class : StringValue::class;
            }
        }

        throw $this->invalid($property, \sprintf('has unsupported object type "%s"', $name));
    }

    /**
     * @param class-string<PrimitiveValueObjectInterface> $rowType
     */
    private function assertRowType(string $rowType, \ReflectionProperty $property): void
    {
        if (!\is_subclass_of($rowType, PrimitiveValueObjectInterface::class)) {
            throw $this->invalid($property, \sprintf(
                'declares row type "%s" that does not implement %s',
                $rowType,
                PrimitiveValueObjectInterface::class
            ));
        }
    }

    private function invalid(\ReflectionProperty $property, string $reason): InvalidRegistryDefinitionException
    {
        return InvalidRegistryDefinitionException::because(\sprintf(
            'Relation field %s::$%s %s.',
            $property->getDeclaringClass()->getName(),
            $property->getName(),
            $reason
        ));
    }
}
