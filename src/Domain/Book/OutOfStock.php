<?php

namespace App\Domain\Book;

final class OutOfStock extends \DomainException
{
    public static function forBook(int $bookId): self
    {
        return new self(\sprintf('Book %d has no copy left in stock.', $bookId));
    }
}
