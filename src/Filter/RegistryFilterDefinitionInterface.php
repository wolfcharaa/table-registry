<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Filter;

/**
 * @api
 */
interface RegistryFilterDefinitionInterface extends \JsonSerializable
{
    public function queryKey(): string;

    public function title(): string;

    public function columnKey(): ?string;

    public function valueFormat(): string;

    /**
     * @return list<string>
     */
    public function operators(): array;

    public function normalize(mixed $value): mixed;

    public function expression(mixed $normalizedValue): RegistryFilterExpressionInterface;
}
