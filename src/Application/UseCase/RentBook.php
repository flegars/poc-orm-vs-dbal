<?php

namespace App\Application\UseCase;

use App\Application\Port\Pricing;
use App\Application\Port\RentalRepository;
use App\Application\Port\Shelf;
use App\Application\Port\Transaction;
use App\Domain\Book\BookNotFound;
use App\Domain\Book\OutOfStock;
use App\Domain\Discount\PriceBasis;
use App\Domain\Rental\Rental;
use Symfony\Component\Uid\Uuid;

final readonly class RentBook
{
    public function __construct(
        private Shelf $shelf,
        private Pricing $pricing,
        private RentalRepository $rentals,
        private Transaction $transaction,
    ) {
    }

    /**
     * @throws BookNotFound
     * @throws OutOfStock
     */
    public function __invoke(int $bookId, \DateTimeImmutable $on): Rental
    {
        return $this->transaction->run(function () use ($bookId, $on): Rental {
            $quote = $this->pricing->quote($bookId, PriceBasis::Rental, $on);
            $this->shelf->takeCopy($bookId);

            $rental = Rental::start(Uuid::v7(), $bookId, $quote, $on);
            $this->rentals->save($rental);

            return $rental;
        });
    }
}
