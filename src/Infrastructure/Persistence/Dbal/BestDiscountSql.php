<?php

namespace App\Infrastructure\Persistence\Dbal;

use App\Domain\Discount\PriceBasis;

final class BestDiscountSql
{
    public static function baseExpression(PriceBasis $basis): string
    {
        return match ($basis) {
            PriceBasis::Sale => 'b.price_in_cents',
            PriceBasis::Rental => \sprintf('ROUND(b.price_in_cents * %d / 100.0)::int', PriceBasis::RENTAL_PERCENT),
        };
    }

    public static function with(string $selectedBooksSql, PriceBasis $basis, bool $materialized): string
    {
        $base = self::baseExpression($basis);
        $materialization = $materialized ? 'MATERIALIZED' : 'NOT MATERIALIZED';

        return <<<SQL
            WITH selected AS {$materialization} (
                {$selectedBooksSql}
            ), active AS MATERIALIZED (
                SELECT * FROM discount WHERE starts_on <= :on AND ends_on >= :on
            ), matched AS (
                SELECT b.id AS book_id, {$base} AS base, a.id AS discount_id, a.kind, a.value
                FROM selected b
                JOIN active a ON a.target = 'genre' AND a.genre = b.genre
                UNION ALL
                SELECT b.id, {$base}, a.id, a.kind, a.value
                FROM selected b
                JOIN active a ON a.target = 'publication_period' AND b.published_on BETWEEN a.published_from AND a.published_to
                UNION ALL
                SELECT b.id, {$base}, a.id, a.kind, a.value
                FROM selected b
                JOIN book_author ba ON ba.book_id = b.id
                JOIN discount_author da ON da.author_id = ba.author_id
                JOIN active a ON a.id = da.discount_id AND a.target = 'authors'
            ), candidate AS (
                SELECT book_id, discount_id,
                       GREATEST(0, base - CASE kind WHEN 'percentage' THEN ROUND(base * value / 100.0)::int ELSE value END) AS final_price
                FROM matched
            ), best AS (
                SELECT DISTINCT ON (book_id) book_id, discount_id, final_price
                FROM candidate ORDER BY book_id, final_price, discount_id
            )
            SQL;
    }
}
