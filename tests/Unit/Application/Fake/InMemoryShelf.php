<?php

namespace App\Tests\Unit\Application\Fake;

use App\Application\Port\Shelf;
use App\Domain\Book\BookNotFound;
use App\Domain\Book\OutOfStock;

final class InMemoryShelf implements Shelf
{
    /**
     * @param array<int, int> $stock
     */
    public function __construct(private array $stock)
    {
    }

    public function takeCopy(int $bookId): void
    {
        $this->assertKnown($bookId);

        if ($this->stock[$bookId] <= 0) {
            throw OutOfStock::forBook($bookId);
        }

        --$this->stock[$bookId];
    }

    public function putCopyBack(int $bookId): void
    {
        $this->assertKnown($bookId);

        ++$this->stock[$bookId];
    }

    public function stockOf(int $bookId): int
    {
        return $this->stock[$bookId];
    }

    private function assertKnown(int $bookId): void
    {
        if (!isset($this->stock[$bookId])) {
            throw BookNotFound::withId($bookId);
        }
    }
}
