<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\TableRegistry\Column\ColumnSelectDefinition;
use Wolfcharaa\TableRegistry\Row\RegistryRowData;
use Wolfcharaa\TableRegistry\Row\RegistryRowInputDefinition;

#[Test]
final class RegistryRowInputDefinitionTest
{
    public function describesSelectedAndExternalInputs(): void
    {
        $select = ColumnSelectDefinition::sqlExpression('departmentTitle', 'd.title');
        $selected = new RegistryRowInputDefinition('departmentTitle', $select);
        $external = new RegistryRowInputDefinition('historyType');

        Assert::same($selected->key(), 'departmentTitle');
        Assert::same($selected->select(), $select);
        Assert::same($external->key(), 'historyType');
        Assert::same($external->select(), null);
    }

    public function rejectsSelectAliasThatDiffersFromInputKey(): void
    {
        Expect::exception(\InvalidArgumentException::class);

        new RegistryRowInputDefinition(
            'departmentTitle',
            ColumnSelectDefinition::sqlExpression('otherTitle', 'd.title')
        );
    }

    public function scopesRowDataAndPreservesExplicitNull(): void
    {
        $row = new RegistryRowData([
            'visible' => 10,
            'nullable' => null,
            'hidden' => 20,
        ]);

        Assert::same($row->only(['nullable', 'visible', 'missing'])->all(), [
            'nullable' => null,
            'visible' => 10,
        ]);
        Assert::same($row->all(), [
            'visible' => 10,
            'nullable' => null,
            'hidden' => 20,
        ]);
    }
}
