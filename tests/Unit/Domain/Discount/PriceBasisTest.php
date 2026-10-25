<?php

namespace App\Tests\Unit\Domain\Discount;

use App\Domain\Discount\PriceBasis;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PriceBasisTest extends TestCase
{
    #[DataProvider('rentalPrices')]
    public function testRentalBaseIsTenPercentRounded(int $bookPrice, int $expected): void
    {
        self::assertSame($expected, PriceBasis::Rental->baseFor($bookPrice));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function rentalPrices(): iterable
    {
        yield 'exact' => [400, 40];
        yield 'half rounds up' => [995, 100];
        yield 'free book' => [0, 0];
    }

    public function testSaleBaseIsTheBookPrice(): void
    {
        self::assertSame(2000, PriceBasis::Sale->baseFor(2000));
    }

    public function testRentalPercentIsTen(): void
    {
        self::assertSame(10, PriceBasis::RENTAL_PERCENT);
    }
}
