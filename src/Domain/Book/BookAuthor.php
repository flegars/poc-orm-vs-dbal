<?php

namespace App\Domain\Book;

class BookAuthor
{
    public function __construct(
        private Book $book,
        private int $authorId,
    ) {
    }

    public function authorId(): int
    {
        return $this->authorId;
    }
}
