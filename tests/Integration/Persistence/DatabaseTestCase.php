<?php

namespace App\Tests\Integration\Persistence;

use App\Application\Bookstore;
use App\Application\BookstoreFactory;
use App\Application\Variant;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class DatabaseTestCase extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection()->executeStatement('TRUNCATE book, author, book_author, discount, discount_author, purchase, rental CASCADE');
    }

    protected function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }

    protected function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function bookstore(Variant $variant): Bookstore
    {
        return self::getContainer()->get(BookstoreFactory::class)->for($variant);
    }

    protected function on(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(ReferenceCatalogue::ON);
    }

    protected function stockOf(int $bookId): int
    {
        return (int) $this->connection()->fetchOne('SELECT stock FROM book WHERE id = ?', [$bookId]);
    }

    protected function rowCount(string $table): int
    {
        return (int) $this->connection()->fetchOne(\sprintf('SELECT COUNT(*) FROM %s', $table));
    }
}
