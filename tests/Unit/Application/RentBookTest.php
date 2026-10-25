<?php

namespace App\Tests\Unit\Application;

use App\Application\UseCase\RentBook;
use App\Domain\Book\BookNotFound;
use App\Domain\Book\OutOfStock;
use App\Domain\Discount\PriceBasis;
use App\Domain\Discount\Quote;
use App\Tests\Unit\Application\Fake\ImmediateTransaction;
use App\Tests\Unit\Application\Fake\InMemoryPricing;
use App\Tests\Unit\Application\Fake\InMemoryRentals;
use App\Tests\Unit\Application\Fake\InMemoryShelf;
use PHPUnit\Framework\TestCase;

final class RentBookTest extends TestCase
{
    private InMemoryShelf $shelf;
    private InMemoryPricing $pricing;
    private InMemoryRentals $rentals;
    private RentBook $rentBook;

    protected function setUp(): void
    {
        $this->shelf = new InMemoryShelf([1 => 2, 2 => 0]);
        $this->pricing = new InMemoryPricing([1 => new Quote(200, 3, 150), 2 => new Quote(300, null, 300)]);
        $this->rentals = new InMemoryRentals();
        $this->rentBook = new RentBook($this->shelf, $this->pricing, $this->rentals, new ImmediateTransaction());
    }

    public function testRentUsesTheRentalBasis(): void
    {
        ($this->rentBook)(1, new \DateTimeImmutable('2026-06-15'));

        self::assertSame(PriceBasis::Rental, $this->pricing->receivedBasis);
    }

    public function testRentTakesACopyAndStartsAFourteenDayRental(): void
    {
        $rental = ($this->rentBook)(1, new \DateTimeImmutable('2026-06-15'));

        self::assertSame(150, $rental->priceInCents());
        self::assertSame(200, $rental->basePriceInCents());
        self::assertSame(3, $rental->discountId());
        self::assertSame('2026-06-29', $rental->dueOn()->format('Y-m-d'));
        self::assertFalse($rental->isReturned());
        self::assertSame(1, $this->shelf->stockOf(1));
        self::assertCount(1, $this->rentals->saved);
    }

    public function testRentOfAnOutOfStockBookSavesNothing(): void
    {
        try {
            ($this->rentBook)(2, new \DateTimeImmutable('2026-06-15'));
            self::fail('Expected OutOfStock.');
        } catch (OutOfStock) {
        }

        self::assertSame([], $this->rentals->saved);
        self::assertSame(0, $this->shelf->stockOf(2));
    }

    public function testRentOfAnUnknownBookSavesNothingAndKeepsStock(): void
    {
        try {
            ($this->rentBook)(99, new \DateTimeImmutable('2026-06-15'));
            self::fail('Expected BookNotFound.');
        } catch (BookNotFound) {
        }

        self::assertSame([], $this->rentals->saved);
        self::assertSame(2, $this->shelf->stockOf(1));
    }
}
