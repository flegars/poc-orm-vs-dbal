<?php

namespace App\Tests\Unit\Application;

use App\Application\UseCase\PurchaseBook;
use App\Domain\Book\BookNotFound;
use App\Domain\Book\OutOfStock;
use App\Domain\Discount\PriceBasis;
use App\Domain\Discount\Quote;
use App\Tests\Unit\Application\Fake\ImmediateTransaction;
use App\Tests\Unit\Application\Fake\InMemoryPricing;
use App\Tests\Unit\Application\Fake\InMemoryPurchases;
use App\Tests\Unit\Application\Fake\InMemoryShelf;
use PHPUnit\Framework\TestCase;

final class PurchaseBookTest extends TestCase
{
    private InMemoryShelf $shelf;
    private InMemoryPricing $pricing;
    private InMemoryPurchases $purchases;
    private PurchaseBook $purchaseBook;

    protected function setUp(): void
    {
        $this->shelf = new InMemoryShelf([1 => 3, 2 => 0]);
        $this->pricing = new InMemoryPricing([1 => new Quote(2000, 5, 1500), 2 => new Quote(1000, null, 1000)]);
        $this->purchases = new InMemoryPurchases();
        $this->purchaseBook = new PurchaseBook($this->shelf, $this->pricing, $this->purchases, new ImmediateTransaction());
    }

    public function testPurchaseTakesACopyAndRecordsThePaidPrice(): void
    {
        $purchase = ($this->purchaseBook)(1, new \DateTimeImmutable('2026-06-15'));

        self::assertSame(1500, $purchase->paidInCents());
        self::assertSame(2000, $purchase->basePriceInCents());
        self::assertSame(5, $purchase->discountId());
        self::assertSame(2, $this->shelf->stockOf(1));
        self::assertSame([$purchase], $this->purchases->saved);
    }

    public function testPurchaseUsesTheSaleBasis(): void
    {
        ($this->purchaseBook)(1, new \DateTimeImmutable('2026-06-15'));

        self::assertSame(PriceBasis::Sale, $this->pricing->receivedBasis);
    }

    public function testPurchaseOfAnOutOfStockBookSavesNothing(): void
    {
        try {
            ($this->purchaseBook)(2, new \DateTimeImmutable('2026-06-15'));
            self::fail('Expected OutOfStock.');
        } catch (OutOfStock) {
        }

        self::assertSame([], $this->purchases->saved);
        self::assertSame(0, $this->shelf->stockOf(2));
    }

    public function testPurchaseOfAnUnknownBookSavesNothingAndKeepsStock(): void
    {
        try {
            ($this->purchaseBook)(99, new \DateTimeImmutable('2026-06-15'));
            self::fail('Expected BookNotFound.');
        } catch (BookNotFound) {
        }

        self::assertSame([], $this->purchases->saved);
        self::assertSame(3, $this->shelf->stockOf(1));
    }
}
