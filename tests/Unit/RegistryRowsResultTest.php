<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\TableRegistry\Row\RegistryRowsProjector;
use Wolfcharaa\TableRegistry\Row\RegistryRowsResult;
use Wolfcharaa\TableRegistry\Sql\RegistrySqlQueryInput;

#[Test]
final class RegistryRowsResultTest extends RegistryTestCase
{
    public function serializesRowsResultContract(): void
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

        Assert::same($payload['schemaVersion'], 2);
        Assert::same($payload['registryKey'], 'users');
        Assert::same($payload['total'], 123);
        Assert::same($payload['rows'], $rows);
        Assert::same($payload['query'], [
            'page' => 2,
            'perPage' => 10,
            'search' => 'иван',
            'sort' => [
                'column' => 'fullName',
                'direction' => 'desc',
            ],
        ]);
    }

    public function rejectsInvalidRowsResultTotal(): void
    {
        Expect::exception(\InvalidArgumentException::class);

        new RegistryRowsResult(
            $this->usersDefinition(),
            RegistrySqlQueryInput::empty($this->usersDefinition()),
            -1,
            []
        );
    }

    public function rejectsRawRows(): void
    {
        Expect::exception(\InvalidArgumentException::class);

        new RegistryRowsResult(
            $this->usersDefinition(),
            RegistrySqlQueryInput::empty($this->usersDefinition()),
            0,
            [['id' => 1]]
        );
    }
}
