<?php

namespace App\Domain\Discount;

class DiscountAuthor
{
    public function __construct(
        private Discount $discount,
        private int $authorId,
    ) {
    }

    public function authorId(): int
    {
        return $this->authorId;
    }
}
