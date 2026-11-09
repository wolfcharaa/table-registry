<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Internal\Attribute;

use Wolfcharaa\TableRegistry\Attribute\ExcludeField;
use Wolfcharaa\TableRegistry\Attribute\Field;
use Wolfcharaa\TableRegistry\Attribute\MappedFields;
use Wolfcharaa\TableRegistry\Attribute\OverrideField;
use Wolfcharaa\TableRegistry\Exception\InvalidRegistryDefinitionException;
use Wolfcharaa\TableRegistry\Metadata\RelationFieldMetadata;

/**
 * @internal
 */
final class MappedFieldCollector
{
    public function __construct(
        private readonly AttributeReader $attributes = new AttributeReader(),
        private readonly FieldTypeResolver $types = new FieldTypeResolver()
    ) {
    }

    /**
     * @param \ReflectionClass<object> $model
     * @return list<RelationFieldMetadata>
     */
    public function collect(\ReflectionClass $model): array
    {
        $fields = [];

        foreach ($this->mappedParents($model) as $parent) {
            $this->addDeclaredFields($fields, $parent);
            foreach ($this->mappedTraits($parent) as $trait) {
                $this->addDeclaredFields($fields, $trait);
            }
        }

        foreach ($this->mappedTraits($model) as $trait) {
            $this->addDeclaredFields($fields, $trait);
        }

        $this->applyExclusions($fields, $model);
        $this->applyOverrides($fields, $model);
        $this->addDeclaredFields($fields, $model);

        return \array_values($fields);
    }

    /**
     * @param \ReflectionClass<object> $model
     * @return list<\ReflectionClass<object>>
     */
    private function mappedParents(\ReflectionClass $model): array
    {
        $parents = [];
        $parent = $model->getParentClass();

        while ($parent !== false) {
            if ($this->attributes->has($parent, MappedFields::class)) {
                $parents[] = $parent;
            }

            $parent = $parent->getParentClass();
        }

        return \array_reverse($parents);
    }

    /**
     * @param \ReflectionClass<object> $source
     * @return list<\ReflectionClass<object>>
     */
    private function mappedTraits(\ReflectionClass $source): array
    {
        $mapped = [];

        foreach ($this->allTraits($source) as $trait) {
            if ($this->attributes->has($trait, MappedFields::class)) {
                $mapped[$trait->getName()] = $trait;
            }
        }

        \ksort($mapped);

        return \array_values($mapped);
    }

    /**
     * @param \ReflectionClass<object> $source
     * @return list<\ReflectionClass<object>>
     */
    private function allTraits(\ReflectionClass $source): array
    {
        $traits = [];

        foreach ($source->getTraits() as $trait) {
            $traits[$trait->getName()] = $trait;
            foreach ($this->allTraits($trait) as $nested) {
                $traits[$nested->getName()] = $nested;
            }
        }

        return \array_values($traits);
    }

    /**
     * @param array<string, RelationFieldMetadata> $fields
     * @param \ReflectionClass<object> $source
     */
    private function addDeclaredFields(array &$fields, \ReflectionClass $source): void
    {
        $traitPropertyNames = $this->traitPropertyNames($source);

        foreach ($source->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() !== $source->getName()) {
                continue;
            }

            if (isset($traitPropertyNames[$property->getName()])) {
                continue;
            }

            $attribute = $this->attributes->first($property, Field::class);
            if (!$attribute instanceof Field) {
                continue;
            }

            if ($property->isStatic()) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Relation field %s::$%s must not be static.',
                    $source->getName(),
                    $property->getName()
                ));
            }

            $name = $property->getName();
            if (isset($fields[$name])) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Duplicate mapped relation field "%s" in %s.',
                    $name,
                    $source->getName()
                ));
            }

            $resolved = $this->types->resolve($property, $attribute);
            $fields[$name] = new RelationFieldMetadata(
                name: $name,
                column: $attribute->column ?? $name,
                rowType: $resolved['rowType'],
                nullable: $resolved['nullable']
            );
        }
    }

    /**
     * @param \ReflectionClass<object> $source
     * @return array<string, true>
     */
    private function traitPropertyNames(\ReflectionClass $source): array
    {
        $names = [];

        foreach ($this->allTraits($source) as $trait) {
            foreach ($trait->getProperties() as $property) {
                $names[$property->getName()] = true;
            }
        }

        return $names;
    }

    /**
     * @param array<string, RelationFieldMetadata> $fields
     * @param \ReflectionClass<object> $model
     */
    private function applyExclusions(array &$fields, \ReflectionClass $model): void
    {
        foreach ($this->attributes->instances($model, ExcludeField::class) as $attribute) {
            if (!$attribute instanceof ExcludeField) {
                continue;
            }

            if (!isset($fields[$attribute->field])) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Excluded relation field "%s" is not imported into %s.',
                    $attribute->field,
                    $model->getName()
                ));
            }

            unset($fields[$attribute->field]);
        }
    }

    /**
     * @param array<string, RelationFieldMetadata> $fields
     * @param \ReflectionClass<object> $model
     */
    private function applyOverrides(array &$fields, \ReflectionClass $model): void
    {
        foreach ($this->attributes->instances($model, OverrideField::class) as $attribute) {
            if (!$attribute instanceof OverrideField) {
                continue;
            }

            $existing = $fields[$attribute->field] ?? null;
            if ($existing === null) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Overridden relation field "%s" is not imported into %s.',
                    $attribute->field,
                    $model->getName()
                ));
            }

            $fields[$attribute->field] = new RelationFieldMetadata(
                name: $existing->name(),
                column: $attribute->column ?? $existing->column(),
                rowType: $attribute->rowType ?? $existing->rowType(),
                nullable: $attribute->nullable ?? $existing->nullable()
            );
        }
    }
}
