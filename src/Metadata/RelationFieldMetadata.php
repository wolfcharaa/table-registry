<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Metadata;

use Wolfcharaa\TableRegistry\Value\PrimitiveValueObjectInterface;

/**
 * @api
 */
final readonly class RelationFieldMetadata
{
    /**
     * @param class-string<PrimitiveValueObjectInterface> $rowType
     */
    public function __construct(
        private string $name,
        private string $column,
        private string $rowType,
        private bool $nullable
    ) {
        if (\trim($name) === '' || \trim($column) === '') {
            throw new \InvalidArgumentException('Relation field name and column must not be empty.');
        }

        if (!\is_subclass_of($rowType, PrimitiveValueObjectInterface::class)) {
            throw new \InvalidArgumentException(\sprintf(
                'Relation field row type "%s" must implement %s.',
                $rowType,
                PrimitiveValueObjectInterface::class
            ));
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function column(): string
    {
        return $this->column;
    }

    /** @return class-string<PrimitiveValueObjectInterface> */
    public function rowType(): string
    {
        return $this->rowType;
    }

    public function nullable(): bool
    {
        return $this->nullable;
    }
}
