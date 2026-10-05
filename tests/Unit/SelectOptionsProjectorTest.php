<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Unit;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\TableRegistry\Exception\OptionProjectionException;
use Wolfcharaa\TableRegistry\Option\OptionListDefinition;
use Wolfcharaa\TableRegistry\Option\SelectOptionsProjector;
use Wolfcharaa\TableRegistry\Select\Definition\SelectDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectFieldDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectSourceDefinition;
use Wolfcharaa\TableRegistry\Select\Definition\SelectValueType;
use Wolfcharaa\TableRegistry\Select\Row\SelectRow;

#[Test]
final class SelectOptionsProjectorTest
{
    public function projectsTypedOptionPayloadWithDeclaredNullableRoles(): void
    {
        $definition = OptionListDefinition::from(
            $this->select(),
            'id',
            'name',
            SelectValueType::Integer
        )
            ->withCode('code')
            ->withDescription('description')
            ->withMeta('active', 'active', SelectValueType::Boolean);

        $options = (new SelectOptionsProjector())->project($definition, [SelectRow::from([
            'id' => '42',
            'name' => 'Подразделение',
            'code' => null,
            'description' => 'Доступное подразделение',
            'active' => '1',
        ])]);

        Assert::same($options[0]->jsonSerialize(), [
            'value' => 42,
            'label' => 'Подразделение',
            'code' => null,
            'description' => 'Доступное подразделение',
            'meta' => ['active' => true],
        ]);
    }

    public function rejectsInvalidRequiredValue(): void
    {
        $definition = OptionListDefinition::from(
            $this->select(),
            'id',
            'name',
            SelectValueType::Integer
        );

        Expect::exception(OptionProjectionException::class);

        (new SelectOptionsProjector())->project($definition, [SelectRow::from([
            'id' => 'not-an-integer',
            'name' => 'Подразделение',
        ])]);
    }

    private function select(): SelectDefinition
    {
        return SelectDefinition::from(
            SelectSourceDefinition::relation('department'),
            [
                SelectFieldDefinition::column('id'),
                SelectFieldDefinition::column('name'),
                SelectFieldDefinition::column('code'),
                SelectFieldDefinition::column('description'),
                SelectFieldDefinition::column('active'),
            ]
        );
    }
}
