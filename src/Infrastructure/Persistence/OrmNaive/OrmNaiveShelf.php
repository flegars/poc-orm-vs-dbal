<?php

namespace App\Infrastructure\Persistence\OrmNaive;

use App\Application\Port\Shelf;
use App\Domain\Book\Book;
use App\Domain\Book\BookNotFound;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OrmNaiveShelf implements Shelf
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function takeCopy(int $bookId): void
    {
        $this->book($bookId)->takeCopy();
        $this->entityManager->flush();
    }

    public function putCopyBack(int $bookId): void
    {
        $this->book($bookId)->putCopyBack();
        $this->entityManager->flush();
    }

    private function book(int $bookId): Book
    {
        return $this->entityManager->find(Book::class, $bookId) ?? throw BookNotFound::withId($bookId);
    }
}
