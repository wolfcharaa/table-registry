<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Internal\Compiler;

use Wolfcharaa\TableRegistry\Attribute\Join;
use Wolfcharaa\TableRegistry\Attribute\JoinType;
use Wolfcharaa\TableRegistry\Attribute\RawJoinCondition;
use Wolfcharaa\TableRegistry\Exception\InvalidRegistryDefinitionException;
use Wolfcharaa\TableRegistry\Internal\Sql\Identifier;
use Wolfcharaa\TableRegistry\Metadata\RelationMetadata;

/**
 * @internal
 */
final class SqlTopologyRenderer
{
    public function source(RelationMetadata $relation, string $alias): string
    {
        return \sprintf('%s AS %s', $this->sourceExpression($relation), Identifier::quote($alias));
    }

    /**
     * @param array<string, RelationMetadata> $participants
     */
    public function join(Join $join, RelationMetadata $relation, array $participants): string
    {
        $condition = $join->on;
        if ($condition instanceof RawJoinCondition) {
            $on = \trim($condition->sql);
            if ($on === '') {
                throw InvalidRegistryDefinitionException::because('Raw JOIN condition must not be empty.');
            }
        } else {
            $parts = [];
            foreach ($condition as $match) {
                $parts[] = \sprintf(
                    '%s = %s',
                    $this->reference($match->left, $participants),
                    $this->reference($match->right, $participants)
                );
            }

            $on = \implode(' AND ', $parts);
        }

        return \sprintf(
            '%s JOIN %s AS %s ON %s',
            $join->type === JoinType::Left ? 'LEFT' : 'INNER',
            $this->sourceExpression($relation),
            Identifier::quote($join->alias),
            $on
        );
    }

    public function field(RelationMetadata $relation, string $alias, string $field): string
    {
        $metadata = $relation->field($field);
        if ($metadata === null) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Participant "%s" does not declare field "%s".',
                $alias,
                $field
            ));
        }

        return \sprintf('%s.%s', Identifier::quote($alias), Identifier::quote($metadata->column()));
    }

    private function sourceExpression(RelationMetadata $relation): string
    {
        $source = $relation->source();
        if ($source->isRelation()) {
            return Identifier::quoteQualified((string)$source->relationIdentifier());
        }

        if ($source->isRaw()) {
            $sql = \trim((string)$source->rawSql());

            return \str_starts_with($sql, '(') && \str_ends_with($sql, ')') ? $sql : '(' . $sql . ')';
        }

        throw new \LogicException('Provider-backed relation source must be resolved before SQL rendering.');
    }

    /**
     * @param array<string, RelationMetadata> $participants
     */
    private function reference(string $reference, array $participants): string
    {
        $parts = \explode('.', $reference);
        if (\count($parts) !== 2 || !Identifier::isAlias($parts[0]) || !Identifier::isAlias($parts[1])) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Structured JOIN reference "%s" must use alias.field.',
                $reference
            ));
        }

        [$alias, $field] = $parts;
        $relation = $participants[$alias] ?? null;
        if ($relation === null) {
            throw InvalidRegistryDefinitionException::because(\sprintf(
                'Structured JOIN references unknown participant alias "%s".',
                $alias
            ));
        }

        return $this->field($relation, $alias, $field);
    }
}
