<?php

namespace App\Domain\Book;

final class BookNotFound extends \DomainException
{
    public static function withId(int $bookId): self
    {
        return new self(\sprintf('Book %d does not exist.', $bookId));
    }
}
