<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Search;

/**
 * @api
 */
interface RegistrySearchDefinitionInterface extends \JsonSerializable
{
    public function queryKey(): string;

    public function isEnabled(): bool;
}
