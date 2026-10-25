<?php

namespace App\Tests\Unit\Domain\Discount;

use App\Domain\Book\Genre;
use App\Domain\Discount\Discount;
use App\Domain\Discount\DiscountKind;
use App\Domain\Discount\DiscountPolicy;
use App\Domain\Discount\Quote;
use PHPUnit\Framework\TestCase;

final class DiscountPolicyTest extends TestCase
{
    private const string ON = '2026-06-15';

    public function testBestPicksLowestFinalPrice(): void
    {
        $percentage = self::discount(1, DiscountKind::Percentage, 25);
        $fiveHundred = self::discount(2, DiscountKind::FixedAmount, 500);
        $threeHundred = self::discount(3, DiscountKind::FixedAmount, 300);

        $best = self::best([$percentage, $fiveHundred, $threeHundred], 1000);

        self::assertSame($fiveHundred, $best);
    }

    public function testBestBreaksTiesOnSmallestId(): void
    {
        $seven = self::discount(7, DiscountKind::FixedAmount, 200);
        $three = self::discount(3, DiscountKind::Percentage, 20);

        self::assertSame($three, self::best([$seven, $three], 1000));
        self::assertSame($three, self::best([$three, $seven], 1000));
    }

    public function testBestIgnoresInactiveAndUntargetedDiscounts(): void
    {
        $expired = Discount::forGenre(
            1,
            DiscountKind::Percentage,
            50,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-06-14'),
            Genre::Fantasy,
        );
        $otherGenre = Discount::forGenre(
            2,
            DiscountKind::Percentage,
            50,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-12-31'),
            Genre::Thriller,
        );

        self::assertNull(self::best([$expired, $otherGenre], 1000));
    }

    public function testBestReturnsNullWithoutDiscounts(): void
    {
        self::assertNull(self::best([], 1000));
    }

    public function testBestCountsDiscountOnceWhenTwoAuthorsAreTargeted(): void
    {
        $discount = Discount::forAuthors(
            5,
            DiscountKind::Percentage,
            10,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-12-31'),
            [1, 2],
        );

        $best = (new DiscountPolicy())->best(
            [$discount],
            Genre::Fantasy,
            new \DateTimeImmutable('2010-05-01'),
            [1, 2],
            1000,
            new \DateTimeImmutable(self::ON),
        );

        self::assertSame($discount, $best);
        self::assertEquals(new Quote(1000, 5, 900), Quote::fromBest(1000, $best));
    }

    public function testBestAcceptsDiscountEndingOnReferenceDate(): void
    {
        $endingToday = Discount::forGenre(
            2,
            DiscountKind::Percentage,
            10,
            new \DateTimeImmutable('2026-06-01'),
            new \DateTimeImmutable(self::ON),
            Genre::Fantasy,
        );

        self::assertSame($endingToday, self::best([$endingToday], 1000));
    }

    public function testBestAcceptsDiscountStartingOnReferenceDate(): void
    {
        $startingToday = Discount::forGenre(
            4,
            DiscountKind::Percentage,
            10,
            new \DateTimeImmutable(self::ON),
            new \DateTimeImmutable('2026-06-30'),
            Genre::Fantasy,
        );

        self::assertSame($startingToday, self::best([$startingToday], 1000));
    }

    public function testBestPrefersOversizedFixedDiscountWhichFloorsAtZero(): void
    {
        $oversized = self::discount(9, DiscountKind::FixedAmount, 5000);
        $half = self::discount(1, DiscountKind::Percentage, 50);

        $best = self::best([$half, $oversized], 400);

        self::assertSame($oversized, $best);
        self::assertSame(0, Quote::fromBest(400, $best)->finalPriceInCents);
    }

    public function testQuoteWithoutDiscountKeepsBasePrice(): void
    {
        self::assertEquals(new Quote(5000, null, 5000), Quote::fromBest(5000, null));
    }

    public function testQuoteWithDiscountCarriesIdAndFinalPrice(): void
    {
        $discount = self::discount(5, DiscountKind::Percentage, 25);

        self::assertEquals(new Quote(2000, 5, 1500), Quote::fromBest(2000, $discount));
    }

    /**
     * @param list<Discount> $discounts
     */
    private static function best(array $discounts, int $basePriceInCents): ?Discount
    {
        return (new DiscountPolicy())->best(
            $discounts,
            Genre::Fantasy,
            new \DateTimeImmutable('2010-05-01'),
            [1],
            $basePriceInCents,
            new \DateTimeImmutable(self::ON),
        );
    }

    private static function discount(int $id, DiscountKind $kind, int $value): Discount
    {
        return Discount::forGenre(
            $id,
            $kind,
            $value,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-12-31'),
            Genre::Fantasy,
        );
    }
}
