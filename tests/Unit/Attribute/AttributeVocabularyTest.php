<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit\Attribute;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\TableRegistry\Attribute\Column;
use Wolfcharaa\TableRegistry\Attribute\CountSource;
use Wolfcharaa\TableRegistry\Attribute\Field;
use Wolfcharaa\TableRegistry\Attribute\FieldMatch;
use Wolfcharaa\TableRegistry\Attribute\Join;
use Wolfcharaa\TableRegistry\Attribute\JoinType;
use Wolfcharaa\TableRegistry\Attribute\OnlyInVariant;
use Wolfcharaa\TableRegistry\Attribute\RawJoinCondition;
use Wolfcharaa\TableRegistry\Attribute\Relation;
use Wolfcharaa\TableRegistry\Attribute\Table;
use Wolfcharaa\TableRegistry\Attribute\TextFilter;
use Wolfcharaa\TableRegistry\Attribute\Variant;

#[Test]
final class AttributeVocabularyTest
{
    public function declaresClassPropertyAndRepeatableTargets(): void
    {
        Assert::same($this->flags(Table::class), \Attribute::TARGET_CLASS);
        Assert::same($this->flags(Relation::class), \Attribute::TARGET_CLASS);
        Assert::same($this->flags(Field::class), \Attribute::TARGET_PROPERTY);
        Assert::same($this->flags(Column::class), \Attribute::TARGET_PROPERTY);
        Assert::same($this->flags(TextFilter::class), \Attribute::TARGET_PROPERTY);
        Assert::same(
            $this->flags(Variant::class),
            \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE
        );
        Assert::same(
            $this->flags(CountSource::class),
            \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE
        );
    }

    public function carriesStructuredAndRawJoinConditions(): void
    {
        $structured = new Join(
            model: DepartmentRelation::class,
            alias: 'department',
            type: JoinType::Left,
            position: 10,
            on: [new FieldMatch('user.departmentId', 'department.id')]
        );
        $raw = new Join(
            model: DepartmentRelation::class,
            alias: 'department',
            type: JoinType::Inner,
            position: 20,
            on: new RawJoinCondition('department.id = user.department_id::uuid')
        );

        Assert::true(\is_array($structured->on));
        Assert::same($structured->on[0]->left, 'user.departmentId');
        Assert::true($raw->on instanceof RawJoinCondition);
        Assert::same($raw->on->sql, 'department.id = user.department_id::uuid');
    }

    public function requiresVariantMembershipToNameAtLeastOneVariant(): void
    {
        Expect::exception(\InvalidArgumentException::class);

        new OnlyInVariant();
    }

    /**
     * @param class-string $attribute
     */
    private function flags(string $attribute): int
    {
        $metadata = (new \ReflectionClass($attribute))->getAttributes(\Attribute::class)[0] ?? null;
        if ($metadata === null) {
            throw new \LogicException(\sprintf('%s must be a native Attribute.', $attribute));
        }

        $definition = $metadata->newInstance();

        return $definition->flags;
    }
}

final class DepartmentRelation
{
}
