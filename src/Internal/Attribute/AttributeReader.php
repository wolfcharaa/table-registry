<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Internal\Attribute;

/**
 * @internal
 */
final class AttributeReader
{
    /**
     * @param \ReflectionClass<object>|\ReflectionProperty $reflection
     * @param class-string $attribute
     * @return list<object>
     */
    public function instances(\ReflectionClass|\ReflectionProperty $reflection, string $attribute): array
    {
        return \array_map(
            static fn (\ReflectionAttribute $metadata): object => $metadata->newInstance(),
            $reflection->getAttributes($attribute, \ReflectionAttribute::IS_INSTANCEOF)
        );
    }

    /**
     * @param \ReflectionClass<object>|\ReflectionProperty $reflection
     * @param class-string $attribute
     */
    public function first(\ReflectionClass|\ReflectionProperty $reflection, string $attribute): ?object
    {
        return $this->instances($reflection, $attribute)[0] ?? null;
    }

    /**
     * @param \ReflectionClass<object>|\ReflectionProperty $reflection
     * @param class-string $attribute
     */
    public function has(\ReflectionClass|\ReflectionProperty $reflection, string $attribute): bool
    {
        return $reflection->getAttributes($attribute, \ReflectionAttribute::IS_INSTANCEOF) !== [];
    }
}
