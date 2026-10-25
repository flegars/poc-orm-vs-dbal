<?php

namespace App\Tests\Integration\Persistence;

use App\Domain\Book\Book;
use App\Domain\Book\Genre;
use App\Domain\Discount\Discount;
use App\Domain\Discount\DiscountKind;
use App\Domain\Rental\Rental;
use App\Domain\Sale\Purchase;
use Symfony\Component\Uid\Uuid;

final class MappingTest extends DatabaseTestCase
{
    public function testBookWithAuthorsRoundTrips(): void
    {
        $em = $this->entityManager();
        $em->persist(new Book(1, 'Charlie', 400, Genre::Fantasy, new \DateTimeImmutable('2024-01-20'), 1, [2, 1]));
        $em->persist(Discount::forAuthors(5, DiscountKind::Percentage, 25, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), [1, 2]));
        $em->flush();
        $em->clear();

        $book = $em->find(Book::class, 1);
        self::assertSame([1, 2], $book->authorIds());
        self::assertSame(Genre::Fantasy, $book->genre());
        self::assertTrue($em->find(Discount::class, 5)->targets(Genre::Poetry, new \DateTimeImmutable('2000-01-01'), [2]));
    }

    public function testPurchaseRoundTripsWithoutDiscount(): void
    {
        $id = Uuid::v7();
        $em = $this->entityManager();
        $em->persist(new Purchase($id, 7, new \DateTimeImmutable('2026-06-15'), 1800, null, 1800));
        $em->flush();
        $em->clear();

        $purchase = $em->find(Purchase::class, $id);

        self::assertTrue($id->equals($purchase->id()));
        self::assertSame(7, $purchase->bookId());
        self::assertSame('2026-06-15', $purchase->purchasedOn()->format('Y-m-d'));
        self::assertSame(1800, $purchase->basePriceInCents());
        self::assertNull($purchase->discountId());
        self::assertSame(1800, $purchase->paidInCents());
    }

    public function testPurchaseRoundTripsWithDiscount(): void
    {
        $id = Uuid::v7();
        $em = $this->entityManager();
        $em->persist(new Purchase($id, 7, new \DateTimeImmutable('2026-06-15'), 2000, 5, 1500));
        $em->flush();
        $em->clear();

        $purchase = $em->find(Purchase::class, $id);

        self::assertSame(5, $purchase->discountId());
        self::assertSame(1500, $purchase->paidInCents());
    }

    public function testOpenRentalRoundTripsWithNullReturnDate(): void
    {
        $id = Uuid::v7();
        $em = $this->entityManager();
        $em->persist(new Rental($id, 3, new \DateTimeImmutable('2026-06-15'), new \DateTimeImmutable('2026-06-29'), 200, null, 200));
        $em->flush();
        $em->clear();

        $rental = $em->find(Rental::class, $id);

        self::assertTrue($id->equals($rental->id()));
        self::assertSame(3, $rental->bookId());
        self::assertSame('2026-06-15', $rental->rentedOn()->format('Y-m-d'));
        self::assertSame('2026-06-29', $rental->dueOn()->format('Y-m-d'));
        self::assertSame(200, $rental->basePriceInCents());
        self::assertNull($rental->discountId());
        self::assertSame(200, $rental->priceInCents());
        self::assertNull($rental->returnedOn());
        self::assertFalse($rental->isReturned());
    }

    public function testReturnedRentalRoundTripsWithDiscountAndReturnDate(): void
    {
        $id = Uuid::v7();
        $em = $this->entityManager();
        $rental = new Rental($id, 3, new \DateTimeImmutable('2026-06-15'), new \DateTimeImmutable('2026-06-29'), 200, 4, 150);
        $rental->markReturned(new \DateTimeImmutable('2026-06-20'));
        $em->persist($rental);
        $em->flush();
        $em->clear();

        $found = $em->find(Rental::class, $id);

        self::assertSame(4, $found->discountId());
        self::assertSame('2026-06-20', $found->returnedOn()?->format('Y-m-d'));
    }
}
