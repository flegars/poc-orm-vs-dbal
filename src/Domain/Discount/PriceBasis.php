<?php

namespace App\Domain\Discount;

enum PriceBasis
{
    case Sale;
    case Rental;

    public const int RENTAL_PERCENT = 10;

    public function baseFor(int $bookPriceInCents): int
    {
        return match ($this) {
            self::Sale => $bookPriceInCents,
            self::Rental => (int) round($bookPriceInCents * self::RENTAL_PERCENT / 100),
        };
    }
}
