<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Wolfcharaa\TableRegistry\Export\RegistryExportColumnResolver;

final class RegistryExportColumnResolverTest extends RegistryTestCase
{
    public function testResolvesRequestedColumnsInsideAllowedSet(): void
    {
        $columns = (new RegistryExportColumnResolver())->resolve(
            $this->usersDefinition(),
            ['id', 'fullName', 'status'],
            ['status', 'missing', 'status', 'fullName']
        );

        self::assertSame(['status', 'fullName'], \array_map(
            static fn ($column): string => $column->key(),
            $columns
        ));
    }

    public function testFallsBackToAllowedColumnsWhenRequestIsEmpty(): void
    {
        $columns = (new RegistryExportColumnResolver())->resolve(
            $this->usersDefinition(),
            ['fullName', 'status'],
            []
        );

        self::assertSame(['fullName', 'status'], \array_map(
            static fn ($column): string => $column->key(),
            $columns
        ));
    }
}

