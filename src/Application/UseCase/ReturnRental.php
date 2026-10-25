<?php

namespace App\Application\UseCase;

use App\Application\Port\RentalRepository;
use App\Application\Port\Shelf;
use App\Application\Port\Transaction;
use App\Domain\Rental\RentalAlreadyReturned;
use App\Domain\Rental\RentalNotFound;
use Symfony\Component\Uid\Uuid;

final readonly class ReturnRental
{
    public function __construct(
        private Shelf $shelf,
        private RentalRepository $rentals,
        private Transaction $transaction,
    ) {
    }

    /**
     * @throws RentalNotFound
     * @throws RentalAlreadyReturned
     */
    public function __invoke(Uuid $rentalId, \DateTimeImmutable $on): void
    {
        $this->transaction->run(function () use ($rentalId, $on): void {
            $rental = $this->rentals->get($rentalId);
            $rental->markReturned($on);
            $this->rentals->save($rental);
            $this->shelf->putCopyBack($rental->bookId());
        });
    }
}
