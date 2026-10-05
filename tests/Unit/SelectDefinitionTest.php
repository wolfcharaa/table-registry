<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\TableRegistry\Exception\InvalidSelectDefinitionException;
use Wolfcharaa\TableRegistry\Select\Definition\SelectDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectFieldDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectOrderDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectPaginationDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectSourceDefinition;

#[Test]
final class SelectDefinitionTest
{
    public function createsImmutableGenericReadDefinition(): void
    {
        $definition = SelectDefinition::from(
            SelectSourceDefinition::relation('public.organization', 'o'),
            [
                SelectFieldDefinition::column('o.id', 'id'),
                SelectFieldDefinition::sqlExpression('label', 'trim(o.name)'),
            ]
        );

        $paginated = $definition
            ->withOrderBy(SelectOrderDefinition::ascending('o.name'))
            ->withPagination(SelectPaginationDefinition::limit(25, 100));

        Assert::null($definition->pagination());
        Assert::same($definition->order(), []);
        Assert::same($paginated->pagination()?->defaultLimit(), 25);
        Assert::same($paginated->fields()[1]->alias(), 'label');
    }

    public function rejectsCaseInsensitiveFieldAliasCollision(): void
    {
        Expect::exception(InvalidSelectDefinitionException::class);

        SelectDefinition::from(
            SelectSourceDefinition::relation('organization'),
            [
                SelectFieldDefinition::column('id'),
                SelectFieldDefinition::sqlExpression('ID', 'legacy_id'),
            ]
        );
    }

    public function requiresOrderBeforePagination(): void
    {
        $definition = SelectDefinition::from(
            SelectSourceDefinition::relation('organization'),
            [SelectFieldDefinition::column('id')]
        );

        Expect::exception(InvalidSelectDefinitionException::class);

        $definition->withPagination(SelectPaginationDefinition::limit());
    }
}
