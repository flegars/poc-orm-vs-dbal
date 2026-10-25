<?php

namespace App\Application\Port;

use App\Domain\Book\BookNotFound;
use App\Domain\Discount\PriceBasis;
use App\Domain\Discount\Quote;

interface Pricing
{
    /**
     * @throws BookNotFound
     */
    public function quote(int $bookId, PriceBasis $basis, \DateTimeImmutable $on): Quote;
}
