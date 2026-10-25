<?php

namespace App\Infrastructure\Seeding;

use App\Domain\Book\Genre;
use App\Domain\Discount\DiscountKind;
use App\Domain\Discount\DiscountTarget;
use App\Domain\Discount\PriceBasis;
use Doctrine\DBAL\Connection;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\Uid\Uuid;

final readonly class CatalogueSeeder
{
    public const string REFERENCE_DATE = '2026-06-15';
    public const array TABLES = ['book', 'author', 'book_author', 'discount', 'discount_author', 'purchase', 'rental'];

    private const int DISCOUNTS = 40;
    private const int CHUNK_SIZE = 1000;
    private const string DAY = 'Y-m-d';

    public function __construct(private Connection $connection)
    {
    }

    public function seed(int $books, int $seed = 42): void
    {
        if ($books < 1) {
            throw new \InvalidArgumentException('The catalogue needs at least one book.');
        }

        $random = new Randomizer(new Mt19937($seed));

        $this->connection->transactional(function () use ($books, $random): void {
            $this->connection->executeStatement('TRUNCATE '.implode(', ', self::TABLES).' CASCADE');

            $authors = max(10, intdiv($books, 10));
            $this->seedAuthors($authors);
            $prices = $this->seedBooks($books, $authors, $random);
            $this->seedDiscounts(self::DISCOUNTS, $authors, $random);
            $this->seedPurchases(intdiv($books, 2), $prices, $random);
            $this->seedRentals(intdiv($books, 5), true, $prices, $random);
            $this->seedRentals(intdiv($books, 5), false, $prices, $random);
        });
    }

    private function seedAuthors(int $authors): void
    {
        $this->insert('author', ['id', 'name'], (function () use ($authors): \Generator {
            for ($id = 1; $id <= $authors; ++$id) {
                yield [$id, \sprintf('Author %06d', $id)];
            }
        })());
    }

    /**
     * @return list<int> book prices indexed by book id - 1
     */
    private function seedBooks(int $books, int $authors, Randomizer $random): array
    {
        $genres = Genre::cases();
        $firstDay = $this->dayNumber('1950-01-01');
        $lastDay = $this->dayNumber('2026-06-01');
        $prices = [];
        $links = [];

        $rows = (function () use ($books, $authors, $random, $genres, $firstDay, $lastDay, &$prices, &$links): \Generator {
            for ($id = 1; $id <= $books; ++$id) {
                $price = $random->getInt(300, 6000);
                $prices[] = $price;
                $roll = $random->getInt(1, 100);
                $count = $roll <= 70 ? 1 : ($roll <= 95 ? 2 : 3);
                $picked = [];
                while (\count($picked) < $count) {
                    $picked[$random->getInt(1, $authors)] = true;
                }
                foreach (array_keys($picked) as $authorId) {
                    $links[] = [$id, $authorId];
                }

                yield [
                    $id,
                    \sprintf('Book %06d', $id),
                    $price,
                    $genres[$random->getInt(0, \count($genres) - 1)]->value,
                    $this->day($random->getInt($firstDay, $lastDay)),
                    $random->getInt(0, 5),
                ];
            }
        })();

        $this->insert('book', ['id', 'title', 'price_in_cents', 'genre', 'published_on', 'stock'], $rows);
        $this->insert('book_author', ['book_id', 'author_id'], $links);

        return $prices;
    }

    private function seedDiscounts(int $discounts, int $authors, Randomizer $random): void
    {
        $genres = Genre::cases();
        $targets = DiscountTarget::cases();
        $reference = $this->dayNumber(self::REFERENCE_DATE);
        $publicationFirst = $this->dayNumber('1950-01-01');
        $publicationLast = $this->dayNumber('2024-12-31');
        $rows = [];
        $links = [];

        for ($id = 1; $id <= $discounts; ++$id) {
            $target = $targets[($id - 1) % \count($targets)];
            $percentage = 1 === $random->getInt(0, 1);
            $window = $random->getInt(1, 100);
            if ($window <= 70) {
                $start = $reference - $random->getInt(0, 300);
                $end = $reference + $random->getInt(0, 300);
            } elseif ($window <= 85) {
                $end = $reference - $random->getInt(1, 300);
                $start = $end - $random->getInt(10, 300);
            } else {
                $start = $reference + $random->getInt(1, 300);
                $end = $start + $random->getInt(10, 300);
            }

            $genre = null;
            $publishedFrom = null;
            $publishedTo = null;
            if (DiscountTarget::Genre === $target) {
                $genre = $genres[$random->getInt(0, \count($genres) - 1)]->value;
            } elseif (DiscountTarget::PublicationPeriod === $target) {
                $from = $random->getInt($publicationFirst, $publicationLast);
                $publishedFrom = $this->day($from);
                $publishedTo = $this->day($from + $random->getInt(2, 10) * 365);
            } else {
                $picked = [];
                $count = $random->getInt(1, 3);
                while (\count($picked) < $count) {
                    $picked[$random->getInt(1, $authors)] = true;
                }
                foreach (array_keys($picked) as $authorId) {
                    $links[] = [$id, $authorId];
                }
            }

            $rows[] = [
                $id,
                $percentage ? DiscountKind::Percentage->value : DiscountKind::FixedAmount->value,
                $percentage ? $random->getInt(5, 50) : $random->getInt(100, 1500),
                $this->day($start),
                $this->day($end),
                $target->value,
                $genre,
                $publishedFrom,
                $publishedTo,
            ];
        }

        $this->insert('discount', ['id', 'kind', 'value', 'starts_on', 'ends_on', 'target', 'genre', 'published_from', 'published_to'], $rows);
        $this->insert('discount_author', ['discount_id', 'author_id'], $links);
    }

    /**
     * @param list<int> $prices
     */
    private function seedPurchases(int $purchases, array $prices, Randomizer $random): void
    {
        $firstDay = $this->dayNumber('2025-01-01');
        $lastDay = $this->dayNumber(self::REFERENCE_DATE);

        $this->insert('purchase', ['id', 'book_id', 'purchased_on', 'base_price_in_cents', 'discount_id', 'paid_in_cents'], (function () use ($purchases, $prices, $random, $firstDay, $lastDay): \Generator {
            for ($i = 0; $i < $purchases; ++$i) {
                $bookId = $random->getInt(1, \count($prices));
                $base = PriceBasis::Sale->baseFor($prices[$bookId - 1]);

                yield [Uuid::v7()->toRfc4122(), $bookId, $this->day($random->getInt($firstDay, $lastDay)), $base, null, $base];
            }
        })());
    }

    /**
     * @param list<int> $prices
     */
    private function seedRentals(int $rentals, bool $returned, array $prices, Randomizer $random): void
    {
        $firstDay = $this->dayNumber($returned ? '2025-01-01' : '2026-06-01');
        $lastDay = $this->dayNumber($returned ? '2026-05-31' : self::REFERENCE_DATE);

        $this->insert('rental', ['id', 'book_id', 'rented_on', 'due_on', 'base_price_in_cents', 'discount_id', 'price_in_cents', 'returned_on'], (function () use ($rentals, $returned, $prices, $random, $firstDay, $lastDay): \Generator {
            for ($i = 0; $i < $rentals; ++$i) {
                $bookId = $random->getInt(1, \count($prices));
                $base = PriceBasis::Rental->baseFor($prices[$bookId - 1]);
                $rentedOn = $random->getInt($firstDay, $lastDay);

                yield [
                    Uuid::v7()->toRfc4122(),
                    $bookId,
                    $this->day($rentedOn),
                    $this->day($rentedOn + 14),
                    $base,
                    null,
                    $base,
                    $returned ? $this->day($rentedOn + $random->getInt(1, 14)) : null,
                ];
            }
        })());
    }

    /**
     * @param list<string>                   $columns
     * @param iterable<list<int|string|null>> $rows
     */
    private function insert(string $table, array $columns, iterable $rows): void
    {
        $width = \count($columns);
        $group = '('.implode(', ', array_fill(0, $width, '?')).')';
        $head = \sprintf('INSERT INTO %s (%s) VALUES ', $table, implode(', ', $columns));
        $batch = [];
        $count = 0;

        foreach ($rows as $row) {
            array_push($batch, ...$row);
            if (++$count === self::CHUNK_SIZE) {
                $this->connection->executeStatement($head.implode(', ', array_fill(0, $count, $group)), $batch);
                $batch = [];
                $count = 0;
            }
        }

        if ($count > 0) {
            $this->connection->executeStatement($head.implode(', ', array_fill(0, $count, $group)), $batch);
        }
    }

    private function dayNumber(string $date): int
    {
        return intdiv((new \DateTimeImmutable($date.' UTC'))->getTimestamp(), 86400);
    }

    private function day(int $dayNumber): string
    {
        return gmdate(self::DAY, $dayNumber * 86400);
    }
}
