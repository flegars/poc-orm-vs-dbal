<?php

namespace App\Application\Port;

use App\Domain\Book\BookNotFound;
use App\Domain\Book\OutOfStock;

interface Shelf
{
    /**
     * @throws BookNotFound
     * @throws OutOfStock
     */
    public function takeCopy(int $bookId): void;

    /**
     * @throws BookNotFound
     */
    public function putCopyBack(int $bookId): void;
}
