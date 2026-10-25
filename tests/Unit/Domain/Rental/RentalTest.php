<?php

namespace App\Tests\Unit\Domain\Rental;

use App\Domain\Discount\Quote;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalAlreadyReturned;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class RentalTest extends TestCase
{
    public function testStartSetsDueDateFourteenDaysLater(): void
    {
        $rental = Rental::start(Uuid::v7(), 4, new Quote(500, null, 500), new \DateTimeImmutable('2026-06-15'));

        self::assertSame('2026-06-29', $rental->dueOn()->format('Y-m-d'));
        self::assertSame(500, $rental->priceInCents());
        self::assertFalse($rental->isReturned());
    }

    public function testStartCrossesMonthBoundary(): void
    {
        $rental = Rental::start(Uuid::v7(), 4, new Quote(500, null, 500), new \DateTimeImmutable('2026-12-25'));

        self::assertSame('2027-01-08', $rental->dueOn()->format('Y-m-d'));
        self::assertSame(14, Rental::DURATION_DAYS);
    }

    public function testStartCopiesQuoteAmountsAndDiscount(): void
    {
        $rental = Rental::start(Uuid::v7(), 4, new Quote(100, 3, 75), new \DateTimeImmutable('2026-06-15'));

        self::assertSame([100, 3, 75], [$rental->basePriceInCents(), $rental->discountId(), $rental->priceInCents()]);
        self::assertSame(4, $rental->bookId());
        self::assertSame('2026-06-15', $rental->rentedOn()->format('Y-m-d'));
        self::assertNull($rental->returnedOn());
    }

    public function testMarkReturnedStoresReturnDate(): void
    {
        $rental = Rental::start(Uuid::v7(), 4, new Quote(500, null, 500), new \DateTimeImmutable('2026-06-15'));

        $rental->markReturned(new \DateTimeImmutable('2026-06-20'));

        self::assertTrue($rental->isReturned());
        self::assertSame('2026-06-20', $rental->returnedOn()?->format('Y-m-d'));
    }

    public function testReturningTwiceIsRefused(): void
    {
        $rental = Rental::start(Uuid::v7(), 4, new Quote(500, null, 500), new \DateTimeImmutable('2026-06-15'));
        $rental->markReturned(new \DateTimeImmutable('2026-06-20'));

        $this->expectException(RentalAlreadyReturned::class);

        $rental->markReturned(new \DateTimeImmutable('2026-06-21'));
    }

    public function testReturningTwiceKeepsFirstReturnDate(): void
    {
        $rental = Rental::start(Uuid::v7(), 4, new Quote(500, null, 500), new \DateTimeImmutable('2026-06-15'));
        $rental->markReturned(new \DateTimeImmutable('2026-06-20'));

        try {
            $rental->markReturned(new \DateTimeImmutable('2026-06-21'));
            self::fail('RentalAlreadyReturned expected.');
        } catch (RentalAlreadyReturned) {
            self::assertSame('2026-06-20', $rental->returnedOn()?->format('Y-m-d'));
        }
    }
}
