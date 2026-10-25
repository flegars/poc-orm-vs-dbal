<?php

namespace App\Infrastructure\Persistence\OrmOptimized;

use App\Application\Port\Shelf;
use App\Domain\Book\Book;
use App\Domain\Book\BookNotFound;
use App\Domain\Book\OutOfStock;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OrmOptimizedShelf implements Shelf
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function takeCopy(int $bookId): void
    {
        $taken = $this->entityManager
            ->createQuery('UPDATE '.Book::class.' b SET b.stock = b.stock - 1 WHERE b.id = :id AND b.stock > 0')
            ->setParameter('id', $bookId)
            ->execute();

        if (1 === $taken) {
            return;
        }

        $exists = $this->entityManager
            ->createQuery('SELECT COUNT(b.id) FROM '.Book::class.' b WHERE b.id = :id')
            ->setParameter('id', $bookId)
            ->getSingleScalarResult() > 0;

        throw $exists ? OutOfStock::forBook($bookId) : BookNotFound::withId($bookId);
    }

    public function putCopyBack(int $bookId): void
    {
        $updated = $this->entityManager
            ->createQuery('UPDATE '.Book::class.' b SET b.stock = b.stock + 1 WHERE b.id = :id')
            ->setParameter('id', $bookId)
            ->execute();

        if (0 === $updated) {
            throw BookNotFound::withId($bookId);
        }
    }
}
