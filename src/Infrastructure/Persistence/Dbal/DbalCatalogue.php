<?php

namespace App\Infrastructure\Persistence\Dbal;

use App\Application\Catalogue\CatalogueEntry;
use App\Application\Port\Catalogue;
use App\Domain\Book\Genre;
use App\Domain\Discount\PriceBasis;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\Types;

final readonly class DbalCatalogue implements Catalogue
{
    public function __construct(private Connection $connection)
    {
    }

    public function export(\DateTimeImmutable $on): iterable
    {
        $result = $this->connection->executeQuery(
            $this->sql('SELECT * FROM book', false),
            ['on' => $on],
            ['on' => Types::DATE_IMMUTABLE],
        );

        yield from $this->entries($result);
    }

    public function page(int $page, int $perPage, \DateTimeImmutable $on): array
    {
        if ($page < 1 || $perPage < 1) {
            throw new \InvalidArgumentException('Page and page size must be positive.');
        }

        $result = $this->connection->executeQuery(
            $this->sql('SELECT * FROM book ORDER BY title, id LIMIT :limit OFFSET :offset', true),
            ['on' => $on, 'limit' => $perPage, 'offset' => ($page - 1) * $perPage],
            ['on' => Types::DATE_IMMUTABLE, 'limit' => Types::INTEGER, 'offset' => Types::INTEGER],
        );

        return iterator_to_array($this->entries($result), false);
    }

    private function sql(string $selectedBooksSql, bool $materialized): string
    {
        return BestDiscountSql::with($selectedBooksSql, PriceBasis::Sale, $materialized).<<<'SQL'

            SELECT b.id, b.title, b.genre, b.price_in_cents, best.discount_id,
                   COALESCE(best.final_price, b.price_in_cents) AS final_price,
                   COALESCE(json_agg(a.name ORDER BY a.name, a.id) FILTER (WHERE a.id IS NOT NULL), '[]') AS author_names
            FROM selected b
            LEFT JOIN best ON best.book_id = b.id
            LEFT JOIN book_author ba ON ba.book_id = b.id
            LEFT JOIN author a ON a.id = ba.author_id
            GROUP BY b.id, b.title, b.genre, b.price_in_cents, best.discount_id, best.final_price
            ORDER BY b.title, b.id
            SQL;
    }

    /**
     * @return \Generator<int, CatalogueEntry>
     */
    private function entries(Result $result): \Generator
    {
        foreach ($result->iterateAssociative() as $row) {
            yield new CatalogueEntry(
                (int) $row['id'],
                $row['title'],
                json_decode($row['author_names'], true, flags: \JSON_THROW_ON_ERROR),
                Genre::from($row['genre']),
                (int) $row['price_in_cents'],
                null === $row['discount_id'] ? null : (int) $row['discount_id'],
                (int) $row['final_price'],
            );
        }
    }
}
