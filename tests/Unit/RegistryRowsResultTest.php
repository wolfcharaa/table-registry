<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Wolfcharaa\TableRegistry\Row\RegistryRowsProjector;
use Wolfcharaa\TableRegistry\Row\RegistryRowsResult;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlQueryInput;

final class RegistryRowsResultTest extends RegistryTestCase
{
    public function testSerializesRowsResultContract(): void
    {
        $definition = $this->usersDefinition();
        $query = RegistrySqlQueryInput::fromArray($definition, [
            'search' => 'иван',
            RegistrySqlQueryInput::SORT_COLUMN_KEY => 'fullName',
            RegistrySqlQueryInput::SORT_DIRECTION_KEY => 'desc',
            'page' => '2',
            'per_page' => '10',
        ]);
        $rows = (new RegistryRowsProjector())->project($definition, [
            [
                'id' => 42,
                'fullName' => 'Иван Петров',
                'status' => 'active',
            ],
        ]);

        $payload = (new RegistryRowsResult($definition, $query, '123', $rows))->jsonSerialize();

        self::assertSame(2, $payload['schemaVersion']);
        self::assertSame('users', $payload['registryKey']);
        self::assertSame(123, $payload['total']);
        self::assertSame($rows, $payload['rows']);
        self::assertSame([
            'page' => 2,
            'perPage' => 10,
            'search' => 'иван',
            'sort' => [
                'column' => 'fullName',
                'direction' => 'desc',
            ],
        ], $payload['query']);
    }

    public function testRejectsInvalidRowsResultTotal(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RegistryRowsResult(
            $this->usersDefinition(),
            RegistrySqlQueryInput::empty($this->usersDefinition()),
            -1,
            []
        );
    }

    public function testRejectsRawRows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RegistryRowsResult(
            $this->usersDefinition(),
            RegistrySqlQueryInput::empty($this->usersDefinition()),
            0,
            [['id' => 1]]
        );
    }
}
