<?php

declare(strict_types=1);

namespace TableRegistry\Tests\Unit;

use TableRegistry\Sql\PostgresIdentifier;
use TableRegistry\Sql\PostgresLiteral;

final class PostgresValueObjectTest extends RegistryTestCase
{
    public function testQuotesPostgresIdentifier(): void
    {
        self::assertSame('"user""name"', PostgresIdentifier::value('user"name')->quoted());
    }

    public function testQuotesPostgresLiterals(): void
    {
        self::assertSame('NULL', PostgresLiteral::value(null)->quoted());
        self::assertSame('TRUE', PostgresLiteral::value(true)->quoted());
        self::assertSame("'\u{0418}ванов O''Neil'", PostgresLiteral::value("Иванов O'Neil")->quoted());
    }
}

