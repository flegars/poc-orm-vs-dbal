<?php

namespace App\Infrastructure\Persistence\Dbal;

use App\Application\Port\Shelf;
use App\Domain\Book\BookNotFound;
use App\Domain\Book\OutOfStock;
use Doctrine\DBAL\Connection;

final readonly class DbalShelf implements Shelf
{
    public function __construct(private Connection $connection)
    {
    }

    public function takeCopy(int $bookId): void
    {
        $taken = $this->connection->executeStatement(
            'UPDATE book SET stock = stock - 1 WHERE id = :id AND stock > 0',
            ['id' => $bookId],
        );

        if (1 === $taken) {
            return;
        }

        $exists = false !== $this->connection->fetchOne('SELECT 1 FROM book WHERE id = :id', ['id' => $bookId]);

        throw $exists ? OutOfStock::forBook($bookId) : BookNotFound::withId($bookId);
    }

    public function putCopyBack(int $bookId): void
    {
        $updated = $this->connection->executeStatement(
            'UPDATE book SET stock = stock + 1 WHERE id = :id',
            ['id' => $bookId],
        );

        if (0 === $updated) {
            throw BookNotFound::withId($bookId);
        }
    }
}
