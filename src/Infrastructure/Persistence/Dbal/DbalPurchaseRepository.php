<?php

namespace App\Infrastructure\Persistence\Dbal;

use App\Application\Port\PurchaseRepository;
use App\Domain\Sale\Purchase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;

final readonly class DbalPurchaseRepository implements PurchaseRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function save(Purchase $purchase): void
    {
        $this->connection->executeStatement(
            'INSERT INTO purchase (id, book_id, purchased_on, base_price_in_cents, discount_id, paid_in_cents)
             VALUES (:id, :book_id, :purchased_on, :base_price, :discount_id, :paid)',
            [
                'id' => $purchase->id()->toRfc4122(),
                'book_id' => $purchase->bookId(),
                'purchased_on' => $purchase->purchasedOn(),
                'base_price' => $purchase->basePriceInCents(),
                'discount_id' => $purchase->discountId(),
                'paid' => $purchase->paidInCents(),
            ],
            [
                'purchased_on' => Types::DATE_IMMUTABLE,
                'discount_id' => Types::INTEGER,
            ],
        );
    }
}
