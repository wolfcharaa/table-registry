<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class Join
{
    /** @var non-empty-list<FieldMatch>|RawJoinCondition */
    public array|RawJoinCondition $on;

    /**
     * @param class-string $model
     * @param non-empty-list<FieldMatch>|RawJoinCondition $on
     */
    public function __construct(
        public string $model,
        public string $alias,
        public JoinType $type,
        public int $position,
        array|RawJoinCondition $on
    ) {
        $this->on = $on instanceof RawJoinCondition ? $on : self::fieldMatches($on);
    }

    /**
     * @param array<array-key, mixed> $matches
     * @return non-empty-list<FieldMatch>
     */
    private static function fieldMatches(array $matches): array
    {
        if ($matches === []) {
            throw new \InvalidArgumentException('Structured JOIN requires at least one FieldMatch.');
        }

        $normalized = [];
        foreach ($matches as $match) {
            if (!$match instanceof FieldMatch) {
                throw new \InvalidArgumentException('Structured JOIN accepts only FieldMatch values.');
            }

            $normalized[] = $match;
        }

        return $normalized;
    }
}
