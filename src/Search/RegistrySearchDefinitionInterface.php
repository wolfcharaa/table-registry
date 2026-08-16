<?php

declare(strict_types=1);

namespace TableRegistry\Search;

interface RegistrySearchDefinitionInterface extends \JsonSerializable
{
    public function queryKey(): string;

    public function isEnabled(): bool;
}
