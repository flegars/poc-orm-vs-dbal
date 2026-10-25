<?php

namespace App\Infrastructure\Persistence\Dbal;

use App\Application\Port\Pricing;
use App\Domain\Book\BookNotFound;
use App\Domain\Discount\PriceBasis;
use App\Domain\Discount\Quote;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

final readonly class DbalPricing implements Pricing
{
    public function __construct(private Connection $connection)
    {
    }

    public function quote(int $bookId, PriceBasis $basis, \DateTimeImmutable $on): Quote
    {
        $base = BestDiscountSql::baseExpression($basis);
        $sql = BestDiscountSql::with('SELECT * FROM book WHERE id = :id', $basis, true).<<<SQL

            SELECT {$base} AS base_price, best.discount_id, COALESCE(best.final_price, {$base}) AS final_price
            FROM selected b
            LEFT JOIN best ON best.book_id = b.id
            SQL;

        $row = $this->connection->fetchAssociative($sql, ['id' => $bookId, 'on' => $on], ['id' => Types::INTEGER, 'on' => Types::DATE_IMMUTABLE]);

        if (false === $row) {
            throw BookNotFound::withId($bookId);
        }

        return new Quote(
            (int) $row['base_price'],
            null === $row['discount_id'] ? null : (int) $row['discount_id'],
            (int) $row['final_price'],
        );
    }
}
