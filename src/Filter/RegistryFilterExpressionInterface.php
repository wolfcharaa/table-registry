<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Filter;

/**
 * @api
 */
interface RegistryFilterExpressionInterface
{
    public function columnKey(): ?string;

    public function operator(): string;

    public function value(): mixed;
}
