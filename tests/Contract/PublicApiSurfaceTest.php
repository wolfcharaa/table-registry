<?php

declare(strict_types=1);

namespace Wolfcharaa\TableRegistry\Tests\Contract;

use Testo\Assert;
use Testo\Test;
use Wolfcharaa\TableRegistry\Tests\Contract\Support\PublicApiInspector;

#[Test]
final class PublicApiSurfaceTest
{
    public function classifiesEverySourceSymbol(): void
    {
        Assert::same($this->inspector()->violations(), []);
    }

    public function matchesReviewedSignatureSnapshot(): void
    {
        $inspector = $this->inspector();

        Assert::same($inspector->snapshot(), $inspector->storedSnapshot());
    }

    private function inspector(): PublicApiInspector
    {
        return new PublicApiInspector(dirname(__DIR__, 2));
    }
}
