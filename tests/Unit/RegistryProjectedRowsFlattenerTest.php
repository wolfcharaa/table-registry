<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Row\RegistryProjectedRowsFlattener;

#[Test]
final class RegistryProjectedRowsFlattenerTest
{
    public function flattensProjectedRowsByLabels(): void
    {
        $rows = RegistryProjectedRowsFlattener::flattenLabels([
            [
                'primaryKey' => '10',
                'values' => [
                    'status' => ['value' => 'ready', 'label' => 'Готово'],
                    'counter' => ['value' => 5, 'label' => '5'],
                ],
            ],
        ], withPrimaryKey: true);

        Assert::same($rows, [
            [
                'primaryKey' => '10',
                'status' => 'Готово',
                'counter' => '5',
            ],
        ]);
    }
}
