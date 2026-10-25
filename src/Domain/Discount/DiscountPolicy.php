<?php

namespace App\Domain\Discount;

use App\Domain\Book\Genre;

final class DiscountPolicy
{
    /**
     * @param iterable<Discount> $discounts
     * @param list<int>          $authorIds
     */
    public function best(
        iterable $discounts,
        Genre $genre,
        \DateTimeImmutable $publishedOn,
        array $authorIds,
        int $basePriceInCents,
        \DateTimeImmutable $on,
    ): ?Discount {
        $best = null;
        $bestPrice = null;

        foreach ($discounts as $discount) {
            if (!$discount->isActiveOn($on) || !$discount->targets($genre, $publishedOn, $authorIds)) {
                continue;
            }

            $price = $discount->priceAfter($basePriceInCents);
            if (null === $best || $price < $bestPrice || ($price === $bestPrice && $discount->id() < $best->id())) {
                $best = $discount;
                $bestPrice = $price;
            }
        }

        return $best;
    }
}
