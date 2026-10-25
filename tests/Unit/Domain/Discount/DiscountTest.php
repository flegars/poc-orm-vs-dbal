<?php

namespace App\Tests\Unit\Domain\Discount;

use App\Domain\Book\Genre;
use App\Domain\Discount\Discount;
use App\Domain\Discount\DiscountKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DiscountTest extends TestCase
{
    public function testPercentageRoundsHalfUp(): void
    {
        $discount = self::genreDiscount(DiscountKind::Percentage, 25);

        self::assertSame(749, $discount->priceAfter(999));
    }

    public function testFixedAmountNeverGoesBelowZero(): void
    {
        $discount = self::genreDiscount(DiscountKind::FixedAmount, 500);

        self::assertSame(0, $discount->priceAfter(400));
    }

    public function testFixedAmountEqualToPriceGivesZero(): void
    {
        self::assertSame(0, self::genreDiscount(DiscountKind::FixedAmount, 400)->priceAfter(400));
    }

    public function testFullPercentageGivesZero(): void
    {
        self::assertSame(0, self::genreDiscount(DiscountKind::Percentage, 100)->priceAfter(1234));
    }

    #[DataProvider('activityDates')]
    public function testActiveWindowIsInclusiveOnBothEnds(string $date, bool $expected): void
    {
        $discount = Discount::forGenre(
            1,
            DiscountKind::Percentage,
            10,
            new \DateTimeImmutable('2026-06-01'),
            new \DateTimeImmutable('2026-06-15'),
            Genre::Fantasy,
        );

        self::assertSame($expected, $discount->isActiveOn(new \DateTimeImmutable($date)));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function activityDates(): iterable
    {
        yield 'last day' => ['2026-06-15', true];
        yield 'first day' => ['2026-06-01', true];
        yield 'day after end' => ['2026-06-16', false];
        yield 'day before start' => ['2026-05-31', false];
    }

    public function testActiveWindowIgnoresTimeComponent(): void
    {
        $discount = Discount::forGenre(
            1,
            DiscountKind::Percentage,
            10,
            new \DateTimeImmutable('2026-06-01 18:00:00'),
            new \DateTimeImmutable('2026-06-15 08:00:00'),
            Genre::Fantasy,
        );

        self::assertTrue($discount->isActiveOn(new \DateTimeImmutable('2026-06-15 23:59:59')));
        self::assertTrue($discount->isActiveOn(new \DateTimeImmutable('2026-06-01 00:00:00')));
    }

    public function testTargetsByGenre(): void
    {
        $discount = self::genreDiscount(DiscountKind::Percentage, 10);
        $publishedOn = new \DateTimeImmutable('2010-05-01');

        self::assertTrue($discount->targets(Genre::Fantasy, $publishedOn, [1]));
        self::assertFalse($discount->targets(Genre::Thriller, $publishedOn, [1]));
    }

    public function testTargetsByAuthors(): void
    {
        $discount = Discount::forAuthors(
            2,
            DiscountKind::Percentage,
            10,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-12-31'),
            [2],
        );
        $publishedOn = new \DateTimeImmutable('2010-05-01');

        self::assertTrue($discount->targets(Genre::Fantasy, $publishedOn, [1, 2]));
        self::assertFalse($discount->targets(Genre::Fantasy, $publishedOn, [1]));
    }

    #[DataProvider('publicationDates')]
    public function testTargetsByPublicationPeriod(string $publishedOn, bool $expected): void
    {
        $discount = Discount::forPublicationPeriod(
            3,
            DiscountKind::Percentage,
            10,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-12-31'),
            new \DateTimeImmutable('2020-01-01'),
            new \DateTimeImmutable('2022-12-31'),
        );

        self::assertSame($expected, $discount->targets(Genre::Fantasy, new \DateTimeImmutable($publishedOn), [1]));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function publicationDates(): iterable
    {
        yield 'inside' => ['2021-03-10', true];
        yield 'last day of period' => ['2022-12-31', true];
        yield 'first day of period' => ['2020-01-01', true];
        yield 'day after period' => ['2023-01-01', false];
        yield 'day before period' => ['2019-12-31', false];
    }

    #[DataProvider('invalidPercentages')]
    public function testPercentageOutsideOneToHundredIsRefused(int $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::genreDiscount(DiscountKind::Percentage, $value);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidPercentages(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-5];
        yield 'above hundred' => [101];
    }

    #[DataProvider('invalidFixedAmounts')]
    public function testNonPositiveFixedAmountIsRefused(int $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::genreDiscount(DiscountKind::FixedAmount, $value);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidFixedAmounts(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-300];
    }

    public function testStartAfterEndIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Discount::forGenre(
            1,
            DiscountKind::Percentage,
            10,
            new \DateTimeImmutable('2026-06-16'),
            new \DateTimeImmutable('2026-06-15'),
            Genre::Fantasy,
        );
    }

    public function testAuthorDiscountWithoutAuthorIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Discount::forAuthors(
            1,
            DiscountKind::Percentage,
            10,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-12-31'),
            [],
        );
    }

    public function testPublicationPeriodStartingAfterItsEndIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Discount::forPublicationPeriod(
            1,
            DiscountKind::Percentage,
            10,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-12-31'),
            new \DateTimeImmutable('2023-01-01'),
            new \DateTimeImmutable('2022-12-31'),
        );
    }

    private static function genreDiscount(DiscountKind $kind, int $value): Discount
    {
        return Discount::forGenre(
            1,
            $kind,
            $value,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-12-31'),
            Genre::Fantasy,
        );
    }
}
