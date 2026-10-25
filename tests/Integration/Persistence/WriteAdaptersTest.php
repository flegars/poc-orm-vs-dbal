<?php

namespace App\Tests\Integration\Persistence;

use App\Application\Variant;
use App\Domain\Book\BookNotFound;
use App\Domain\Book\OutOfStock;
use App\Domain\Rental\RentalAlreadyReturned;
use App\Domain\Rental\RentalNotFound;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;

final class WriteAdaptersTest extends DatabaseTestCase
{
    use ImplementedVariants;

    protected function setUp(): void
    {
        parent::setUp();
        ReferenceCatalogue::load($this->connection());
    }

    #[DataProvider('variants')]
    public function testPurchaseAppliesTheBestDiscount(Variant $variant): void
    {
        $purchase = ($this->bookstore($variant)->purchaseBook)(1, $this->on());

        self::assertSame([2000, 5, 1500], [$purchase->basePriceInCents(), $purchase->discountId(), $purchase->paidInCents()]);
        self::assertSame(2, $this->stockOf(1));
        self::assertSame(1, $this->rowCount('purchase'));
        $row = $this->connection()->fetchAssociative('SELECT * FROM purchase');
        self::assertSame($purchase->id()->toRfc4122(), $row['id']);
        self::assertSame(
            [1, '2026-06-15', 2000, 5, 1500],
            [$row['book_id'], $row['purchased_on'], $row['base_price_in_cents'], $row['discount_id'], $row['paid_in_cents']],
        );
    }

    #[DataProvider('variants')]
    public function testPurchaseOfAnOutOfStockBookWritesNothing(Variant $variant): void
    {
        $this->expectException(OutOfStock::class);

        try {
            ($this->bookstore($variant)->purchaseBook)(2, $this->on());
        } finally {
            self::assertSame(0, $this->stockOf(2));
            self::assertSame(0, $this->rowCount('purchase'));
        }
    }

    #[DataProvider('variants')]
    public function testPurchaseOfAnUnknownBookIsRefused(Variant $variant): void
    {
        $this->expectException(BookNotFound::class);

        try {
            ($this->bookstore($variant)->purchaseBook)(99, $this->on());
        } finally {
            self::assertSame(0, $this->rowCount('purchase'));
        }
    }

    #[DataProvider('variants')]
    public function testRentalAppliesTheBestDiscountOnTheRentalBase(Variant $variant): void
    {
        $rental = ($this->bookstore($variant)->rentBook)(3, $this->on());

        self::assertSame([40, 2, 0], [$rental->basePriceInCents(), $rental->discountId(), $rental->priceInCents()]);
        self::assertSame('2026-06-29', $rental->dueOn()->format('Y-m-d'));
        self::assertSame(0, $this->stockOf(3));
        $row = $this->connection()->fetchAssociative('SELECT * FROM rental WHERE id = ?', [$rental->id()->toRfc4122()]);
        self::assertSame(
            [3, '2026-06-15', '2026-06-29', 40, 2, 0, null],
            [$row['book_id'], $row['rented_on'], $row['due_on'], $row['base_price_in_cents'], $row['discount_id'], $row['price_in_cents'], $row['returned_on']],
        );
        self::assertSame(2, $this->rowCount('rental'));
    }

    #[DataProvider('variants')]
    public function testRentalWithoutDiscountChargesTheRentalBase(Variant $variant): void
    {
        $rental = ($this->bookstore($variant)->rentBook)(4, $this->on());

        self::assertSame([500, null, 500], [$rental->basePriceInCents(), $rental->discountId(), $rental->priceInCents()]);
        self::assertSame(1, $this->stockOf(4));
        $row = $this->connection()->fetchAssociative('SELECT * FROM rental WHERE id = ?', [$rental->id()->toRfc4122()]);
        self::assertSame([4, 500, null, 500], [$row['book_id'], $row['base_price_in_cents'], $row['discount_id'], $row['price_in_cents']]);
    }

    #[DataProvider('variants')]
    public function testReturnClosesTheRentalAndPutsTheCopyBack(Variant $variant): void
    {
        $bookstore = $this->bookstore($variant);

        ($bookstore->returnRental)(Uuid::fromString(ReferenceCatalogue::OPEN_RENTAL_ID), $this->on());

        self::assertSame('2026-06-15', $this->connection()->fetchOne('SELECT returned_on FROM rental WHERE id = ?', [ReferenceCatalogue::OPEN_RENTAL_ID]));
        self::assertSame(3, $this->stockOf(4));
    }

    #[DataProvider('variants')]
    public function testSecondReturnIsRefusedAndLeavesStockUnchanged(Variant $variant): void
    {
        $bookstore = $this->bookstore($variant);
        ($bookstore->returnRental)(Uuid::fromString(ReferenceCatalogue::OPEN_RENTAL_ID), $this->on());

        try {
            ($bookstore->returnRental)(Uuid::fromString(ReferenceCatalogue::OPEN_RENTAL_ID), $this->on());
            self::fail('RentalAlreadyReturned expected');
        } catch (RentalAlreadyReturned) {
            self::assertSame(3, $this->stockOf(4));
        }
    }

    #[DataProvider('variants')]
    public function testReturnOfAnUnknownRentalIsRefused(Variant $variant): void
    {
        $this->expectException(RentalNotFound::class);

        ($this->bookstore($variant)->returnRental)(Uuid::fromString('01900000-0000-7000-8000-0000000000ff'), $this->on());
    }
}
