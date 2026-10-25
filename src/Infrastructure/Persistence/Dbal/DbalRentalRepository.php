<?php

namespace App\Infrastructure\Persistence\Dbal;

use App\Application\Port\RentalRepository;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalNotFound;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Uid\Uuid;

final readonly class DbalRentalRepository implements RentalRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function get(Uuid $id): Rental
    {
        $row = $this->connection->fetchAssociative(
            'SELECT book_id, rented_on, due_on, base_price_in_cents, discount_id, price_in_cents, returned_on FROM rental WHERE id = :id',
            ['id' => $id->toRfc4122()],
        );

        if (false === $row) {
            throw RentalNotFound::withId($id);
        }

        return new Rental(
            $id,
            (int) $row['book_id'],
            self::date($row['rented_on']),
            self::date($row['due_on']),
            (int) $row['base_price_in_cents'],
            null === $row['discount_id'] ? null : (int) $row['discount_id'],
            (int) $row['price_in_cents'],
            null === $row['returned_on'] ? null : self::date($row['returned_on']),
        );
    }

    public function save(Rental $rental): void
    {
        $this->connection->executeStatement(
            'INSERT INTO rental (id, book_id, rented_on, due_on, base_price_in_cents, discount_id, price_in_cents, returned_on)
             VALUES (:id, :book_id, :rented_on, :due_on, :base_price, :discount_id, :price, :returned_on)
             ON CONFLICT (id) DO UPDATE SET returned_on = EXCLUDED.returned_on',
            [
                'id' => $rental->id()->toRfc4122(),
                'book_id' => $rental->bookId(),
                'rented_on' => $rental->rentedOn(),
                'due_on' => $rental->dueOn(),
                'base_price' => $rental->basePriceInCents(),
                'discount_id' => $rental->discountId(),
                'price' => $rental->priceInCents(),
                'returned_on' => $rental->returnedOn(),
            ],
            [
                'rented_on' => Types::DATE_IMMUTABLE,
                'due_on' => Types::DATE_IMMUTABLE,
                'discount_id' => Types::INTEGER,
                'returned_on' => Types::DATE_IMMUTABLE,
            ],
        );
    }

    private static function date(string $value): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('!Y-m-d', $value) ?: throw new \UnexpectedValueException(\sprintf('Invalid date "%s".', $value));
    }
}
