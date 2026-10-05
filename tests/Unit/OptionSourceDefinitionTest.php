<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Option\OptionSourceDefinition;
use Wolfcharaa\TableRegistry\Option\SelectOption;

#[Test]
final class OptionSourceDefinitionTest
{
    public function describesFrontendDeliverySeparatelyFromBackendSelection(): void
    {
        Assert::same(OptionSourceDefinition::preload('/options/regions')->jsonSerialize(), [
            'optionSource' => 'preload',
            'route' => '/options/regions',
        ]);

        Assert::same(OptionSourceDefinition::async('/options/regions')->jsonSerialize(), [
            'optionSource' => 'async',
            'route' => '/options/regions',
        ]);

        Assert::same(OptionSourceDefinition::static([
            SelectOption::from('active', 'Активные'),
            ['value' => 'blocked', 'label' => 'Заблокированные', 'color' => 'red'],
        ])->jsonSerialize(), [
            'optionSource' => 'static',
            'options' => [
                ['value' => 'active', 'label' => 'Активные'],
                ['value' => 'blocked', 'label' => 'Заблокированные', 'color' => 'red'],
            ],
        ]);
    }
}
