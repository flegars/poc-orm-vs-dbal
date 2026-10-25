<?php

namespace App\Application;

use App\Application\Port\Transaction;
use App\Application\Port\VariantAdapters;
use App\Application\UseCase\PurchaseBook;
use App\Application\UseCase\RentBook;
use App\Application\UseCase\ReturnRental;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class BookstoreFactory
{
    /**
     * @param iterable<VariantAdapters> $adapters
     */
    public function __construct(
        #[AutowireIterator(VariantAdapters::class)]
        private iterable $adapters,
        private Transaction $transaction,
    ) {
    }

    /**
     * @throws \LogicException
     */
    public function for(Variant $variant): Bookstore
    {
        foreach ($this->adapters as $adapters) {
            if ($adapters->variant() !== $variant) {
                continue;
            }

            return new Bookstore(
                new PurchaseBook($adapters->shelf(), $adapters->pricing(), $adapters->purchases(), $this->transaction),
                new RentBook($adapters->shelf(), $adapters->pricing(), $adapters->rentals(), $this->transaction),
                new ReturnRental($adapters->shelf(), $adapters->rentals(), $this->transaction),
                $adapters->catalogue(),
            );
        }

        throw new \LogicException(\sprintf('No adapters registered for variant "%s".', $variant->value));
    }
}
