<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Metadata;

use Wolfcharaa\TableRegistry\Attribute\MappedFields;
use Wolfcharaa\TableRegistry\Attribute\RawRelation;
use Wolfcharaa\TableRegistry\Attribute\Relation;
use Wolfcharaa\TableRegistry\Attribute\RelationSourceProvider;
use Wolfcharaa\TableRegistry\Exception\InvalidRegistryDefinitionException;
use Wolfcharaa\TableRegistry\Internal\Attribute\AttributeReader;
use Wolfcharaa\TableRegistry\Internal\Attribute\MappedFieldCollector;
use Wolfcharaa\TableRegistry\Internal\Sql\Identifier;

/**
 * @api
 */
final class AttributeRelationMetadataProvider implements RelationMetadataProviderInterface
{
    private readonly AttributeReader $attributes;
    private readonly MappedFieldCollector $fields;

    public function __construct()
    {
        $this->attributes = new AttributeReader();
        $this->fields = new MappedFieldCollector();
    }

    public function metadata(string $model): ?RelationMetadata
    {
        if (!\class_exists($model)) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Relation model "%s" must be an existing class.',
                $model
            ));
        }

        $reflection = new \ReflectionClass($model);
        $sources = [
            ...$this->attributes->instances($reflection, Relation::class),
            ...$this->attributes->instances($reflection, RawRelation::class),
            ...$this->attributes->instances($reflection, RelationSourceProvider::class),
        ];

        if ($sources === []) {
            return null;
        }

        if (\count($sources) !== 1) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Relation model "%s" must declare exactly one relation source.',
                $model
            ));
        }

        if ($this->attributes->has($reflection, MappedFields::class)) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Relation model "%s" cannot also be a mapped-field source.',
                $model
            ));
        }

        return new RelationMetadata(
            source: $this->source($sources[0], $model),
            fields: $this->fields->collect($reflection)
        );
    }

    private function source(object $source, string $model): RelationSourceMetadata
    {
        if ($source instanceof Relation) {
            if (!Identifier::isQualified($source->identifier)) {
                throw InvalidRegistryDefinitionException::because(\sprintf(
                    'Relation model "%s" identifier "%s" must be qualified SQL identifiers.',
                    $model,
                    $source->identifier
                ));
            }

            return RelationSourceMetadata::relation($source->identifier, $source->connection);
        }

        if ($source instanceof RawRelation) {
            return RelationSourceMetadata::raw($source->sql, $source->connection);
        }

        if ($source instanceof RelationSourceProvider) {
            return RelationSourceMetadata::providedBy($source->provider);
        }

        throw new \LogicException('Unsupported relation source Attribute.');
    }
}
