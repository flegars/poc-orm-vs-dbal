<?php

namespace App\Tests\Unit\Domain\Book;

use App\Domain\Book\Book;
use App\Domain\Book\Genre;
use App\Domain\Book\OutOfStock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BookTest extends TestCase
{
    public function testTakeCopyDecrementsStock(): void
    {
        $book = new Book(1, 'Alpha', 2000, Genre::Fantasy, new \DateTimeImmutable('2010-05-01'), 1, [3, 1]);

        $book->takeCopy();

        self::assertSame(0, $book->stock());
        self::assertSame([1, 3], $book->authorIds());
    }

    public function testTakeCopyRefusesWhenStockIsEmpty(): void
    {
        $book = new Book(2, 'Bravo', 1000, Genre::ScienceFiction, new \DateTimeImmutable('2021-03-10'), 0, [2]);

        $this->expectException(OutOfStock::class);

        $book->takeCopy();
    }

    public function testTakeCopyWhenStockIsEmptyLeavesStockUntouched(): void
    {
        $book = new Book(2, 'Bravo', 1000, Genre::ScienceFiction, new \DateTimeImmutable('2021-03-10'), 0, [2]);

        try {
            $book->takeCopy();
            self::fail('OutOfStock expected.');
        } catch (OutOfStock) {
            self::assertSame(0, $book->stock());
        }
    }

    public function testPutCopyBackIncrementsStock(): void
    {
        $book = new Book(3, 'Charlie', 1500, Genre::Thriller, new \DateTimeImmutable('2018-09-20'), 0, [4]);

        $book->putCopyBack();

        self::assertSame(1, $book->stock());
    }

    public function testBookNeedsAtLeastOneAuthor(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Book(4, 'Delta', 1800, Genre::History, new \DateTimeImmutable('2005-01-15'), 3, []);
    }

    public function testBookRefusesNegativePrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Book(5, 'Echo', -1, Genre::Poetry, new \DateTimeImmutable('1999-11-30'), 3, [1]);
    }

    public function testBookRefusesNegativeStock(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Book(6, 'Foxtrot', 1200, Genre::Children, new \DateTimeImmutable('2012-07-04'), -1, [1]);
    }

    public function testAuthorIdsAreDeduplicated(): void
    {
        $book = new Book(7, 'Golf', 900, Genre::Biography, new \DateTimeImmutable('2015-02-02'), 2, [5, 2, 5]);

        self::assertSame([2, 5], $book->authorIds());
    }

    #[DataProvider('zeroBoundaries')]
    public function testBookAcceptsZeroPriceAndZeroStock(int $price, int $stock): void
    {
        $book = new Book(8, 'Hotel', $price, Genre::Romance, new \DateTimeImmutable('2020-12-25'), $stock, [1]);

        self::assertSame($price, $book->priceInCents());
        self::assertSame($stock, $book->stock());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function zeroBoundaries(): iterable
    {
        yield 'free book' => [0, 5];
        yield 'sold out book' => [1500, 0];
    }
}
