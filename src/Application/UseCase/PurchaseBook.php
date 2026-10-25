<?php

namespace App\Application\UseCase;

use App\Application\Port\Pricing;
use App\Application\Port\PurchaseRepository;
use App\Application\Port\Shelf;
use App\Application\Port\Transaction;
use App\Domain\Book\BookNotFound;
use App\Domain\Book\OutOfStock;
use App\Domain\Discount\PriceBasis;
use App\Domain\Sale\Purchase;
use Symfony\Component\Uid\Uuid;

final readonly class PurchaseBook
{
    public function __construct(
        private Shelf $shelf,
        private Pricing $pricing,
        private PurchaseRepository $purchases,
        private Transaction $transaction,
    ) {
    }

    /**
     * @throws BookNotFound
     * @throws OutOfStock
     */
    public function __invoke(int $bookId, \DateTimeImmutable $on): Purchase
    {
        return $this->transaction->run(function () use ($bookId, $on): Purchase {
            $quote = $this->pricing->quote($bookId, PriceBasis::Sale, $on);
            $this->shelf->takeCopy($bookId);

            $purchase = Purchase::record(Uuid::v7(), $bookId, $quote, $on);
            $this->purchases->save($purchase);

            return $purchase;
        });
    }
}
