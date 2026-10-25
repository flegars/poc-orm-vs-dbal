<?php

namespace App\Tests\Integration\Persistence;

use Doctrine\DBAL\Connection;

final class ReferenceCatalogue
{
    public const string ON = '2026-06-15';
    public const string OPEN_RENTAL_ID = '01900000-0000-7000-8000-000000000001';

    public static function load(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            INSERT INTO author (id, name) VALUES
                (1, 'Ada Martin'),
                (2, 'Bruno Petit'),
                (3, 'Chloé Durand')
            SQL);
        $connection->executeStatement(<<<'SQL'
            INSERT INTO book (id, title, price_in_cents, genre, published_on, stock) VALUES
                (1, 'Alpha', 2000, 'fantasy', '2010-05-01', 3),
                (2, 'Bravo', 1000, 'science_fiction', '2021-03-10', 0),
                (3, 'Charlie', 400, 'fantasy', '2024-01-20', 1),
                (4, 'Delta', 5000, 'history', '1999-11-11', 2)
            SQL);
        $connection->executeStatement(<<<'SQL'
            INSERT INTO book_author (book_id, author_id) VALUES (1, 1), (2, 2), (3, 1), (3, 2), (4, 3)
            SQL);
        $connection->executeStatement(<<<'SQL'
            INSERT INTO discount (id, kind, value, starts_on, ends_on, target, genre, published_from, published_to) VALUES
                (1, 'percentage', 10, '2026-01-01', '2026-12-31', 'genre', 'fantasy', NULL, NULL),
                (2, 'fixed_amount', 500, '2026-06-01', '2026-06-15', 'authors', NULL, NULL, NULL),
                (3, 'percentage', 50, '2025-01-01', '2025-12-31', 'genre', 'history', NULL, NULL),
                (4, 'fixed_amount', 300, '2026-06-15', '2026-07-31', 'publication_period', NULL, '2020-01-01', '2022-12-31'),
                (5, 'percentage', 25, '2026-01-01', '2026-12-31', 'authors', NULL, NULL, NULL)
            SQL);
        $connection->executeStatement(<<<'SQL'
            INSERT INTO discount_author (discount_id, author_id) VALUES (2, 2), (5, 1), (5, 2)
            SQL);
        $connection->executeStatement(<<<'SQL'
            INSERT INTO rental (id, book_id, rented_on, due_on, base_price_in_cents, discount_id, price_in_cents, returned_on)
            VALUES ('01900000-0000-7000-8000-000000000001', 4, '2026-06-01', '2026-06-15', 500, NULL, 500, NULL)
            SQL);
    }
}
