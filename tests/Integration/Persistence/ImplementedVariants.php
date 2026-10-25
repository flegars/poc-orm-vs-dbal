<?php

namespace App\Tests\Integration\Persistence;

use App\Application\Variant;

trait ImplementedVariants
{
    /**
     * @return iterable<string, array{Variant}>
     */
    public static function variants(): iterable
    {
        foreach (Variant::cases() as $variant) {
            yield $variant->value => [$variant];
        }
    }
}
