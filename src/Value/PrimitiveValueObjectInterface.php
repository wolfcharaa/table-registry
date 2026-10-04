<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Value;

/**
 * @api
 */
interface PrimitiveValueObjectInterface extends \Stringable
{
    public static function value(mixed $value): static;

    public function getValue(): mixed;
}
