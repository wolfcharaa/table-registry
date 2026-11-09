<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Attribute;

/**
 * @api
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final readonly class Join
{
    /**
     * @param class-string $model
     * @param non-empty-list<FieldMatch>|RawJoinCondition $on
     */
    public function __construct(
        public string $model,
        public string $alias,
        public JoinType $type,
        public int $position,
        public array|RawJoinCondition $on
    ) {
    }
}
