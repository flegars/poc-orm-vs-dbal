<?php

namespace App\Tests\Unit\Application\Fake;

use App\Application\Port\Pricing;
use App\Domain\Book\BookNotFound;
use App\Domain\Discount\PriceBasis;
use App\Domain\Discount\Quote;

final class InMemoryPricing implements Pricing
{
    public ?PriceBasis $receivedBasis = null;

    /**
     * @param array<int, Quote> $quotes
     */
    public function __construct(private readonly array $quotes)
    {
    }

    public function quote(int $bookId, PriceBasis $basis, \DateTimeImmutable $on): Quote
    {
        $this->receivedBasis = $basis;

        return $this->quotes[$bookId] ?? throw BookNotFound::withId($bookId);
    }
}
