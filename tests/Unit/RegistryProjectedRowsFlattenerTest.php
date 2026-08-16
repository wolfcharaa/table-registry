<?php

declare(strict_types=1);

namespace TableRegistry\Tests\Unit;

use TableRegistry\Row\RegistryProjectedRowsFlattener;

final class RegistryProjectedRowsFlattenerTest extends RegistryTestCase
{
    public function testFlattensProjectedRowsByLabels(): void
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

        self::assertSame([
            [
                'primaryKey' => '10',
                'status' => 'Готово',
                'counter' => '5',
            ],
        ], $rows);
    }
}

