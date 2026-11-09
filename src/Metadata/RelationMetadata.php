<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Metadata;

/**
 * @api
 */
final class RelationMetadata
{
    /** @var list<RelationFieldMetadata> */
    private array $fields;

    /**
     * @param iterable<RelationFieldMetadata> $fields
     */
    public function __construct(
        private readonly RelationSourceMetadata $source,
        iterable $fields
    ) {
        $normalized = [];
        $names = [];

        foreach ($fields as $field) {
            if (isset($names[$field->name()])) {
                throw new \InvalidArgumentException(\sprintf(
                    'Duplicate relation field "%s".',
                    $field->name()
                ));
            }

            $names[$field->name()] = true;
            $normalized[] = $field;
        }

        if ($normalized === []) {
            throw new \InvalidArgumentException('Relation metadata must contain at least one field.');
        }

        $this->fields = $normalized;
    }

    public function source(): RelationSourceMetadata
    {
        return $this->source;
    }

    /** @return list<RelationFieldMetadata> */
    public function fields(): array
    {
        return $this->fields;
    }

    public function field(string $name): ?RelationFieldMetadata
    {
        foreach ($this->fields as $field) {
            if ($field->name() === $name) {
                return $field;
            }
        }

        return null;
    }
}
