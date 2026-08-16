<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Filter;

interface RegistryFilterExpressionInterface
{
    public function columnKey(): ?string;

    public function operator(): string;

    public function value(): mixed;
}
