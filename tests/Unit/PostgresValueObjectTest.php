<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Sql\PostgresIdentifier;
use Wolfcharaa\TableRegistry\Sql\PostgresLiteral;

#[Test]
final class PostgresValueObjectTest
{
    public function quotesPostgresIdentifier(): void
    {
        Assert::same(PostgresIdentifier::value('user"name')->quoted(), '"user""name"');
    }

    public function quotesPostgresLiterals(): void
    {
        Assert::same(PostgresLiteral::value(null)->quoted(), 'NULL');
        Assert::same(PostgresLiteral::value(true)->quoted(), 'TRUE');
        Assert::same(PostgresLiteral::value("Иванов O'Neil")->quoted(), "'\u{0418}ванов O''Neil'");
    }
}
