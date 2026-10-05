<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\TableRegistry\Exception\SelectProjectionException;
use Wolfcharaa\TableRegistry\Select\Definition\SelectDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectFieldDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectSourceDefinition;
use Wolfcharaa\TableRegistry\Select\Row\SelectRowsProjector;

#[Test]
final class SelectRowsProjectorTest
{
    public function projectsOnlyDeclaredAliasesInDefinitionOrder(): void
    {
        $definition = $this->definition();

        $rows = (new SelectRowsProjector())->project($definition, [[
            'label' => 'Москва',
            'value' => 77,
            'unexpected' => 'discarded',
        ]]);

        Assert::same($rows[0]->all(), ['value' => 77, 'label' => 'Москва']);
    }

    public function rejectsMissingDeclaredAliasButPreservesNull(): void
    {
        $projector = new SelectRowsProjector();
        $rows = $projector->project($this->definition(), [['value' => 77, 'label' => null]]);

        Assert::null($rows[0]->get('label'));

        Expect::exception(SelectProjectionException::class);
        $projector->project($this->definition(), [['value' => 77]]);
    }

    private function definition(): SelectDefinition
    {
        return SelectDefinition::from(
            SelectSourceDefinition::relation('region'),
            [
                SelectFieldDefinition::column('id', 'value'),
                SelectFieldDefinition::column('name', 'label'),
            ]
        );
    }
}
