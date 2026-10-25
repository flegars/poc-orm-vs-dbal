<?php

namespace App\Domain\Discount;

final readonly class Quote
{
    public function __construct(
        public int $basePriceInCents,
        public ?int $discountId,
        public int $finalPriceInCents,
    ) {
    }

    public static function fromBest(int $basePriceInCents, ?Discount $best): self
    {
        if (null === $best) {
            return new self($basePriceInCents, null, $basePriceInCents);
        }

        return new self($basePriceInCents, $best->id(), $best->priceAfter($basePriceInCents));
    }
}
