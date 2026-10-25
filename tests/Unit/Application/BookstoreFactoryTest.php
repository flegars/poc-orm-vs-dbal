<?php

namespace App\Tests\Unit\Application;

use App\Application\BookstoreFactory;
use App\Application\Port\Catalogue;
use App\Application\Port\Pricing;
use App\Application\Port\PurchaseRepository;
use App\Application\Port\RentalRepository;
use App\Application\Port\Shelf;
use App\Application\Port\VariantAdapters;
use App\Application\Variant;
use App\Domain\Discount\Quote;
use App\Tests\Unit\Application\Fake\ImmediateTransaction;
use App\Tests\Unit\Application\Fake\InMemoryPricing;
use App\Tests\Unit\Application\Fake\InMemoryPurchases;
use App\Tests\Unit\Application\Fake\InMemoryRentals;
use App\Tests\Unit\Application\Fake\InMemoryShelf;
use PHPUnit\Framework\TestCase;

final class BookstoreFactoryTest extends TestCase
{
    public function testForVariantWithoutAdaptersThrowsLogicException(): void
    {
        $factory = new BookstoreFactory([], new ImmediateTransaction());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No adapters registered for variant "dbal".');

        $factory->for(Variant::Dbal);
    }

    public function testForVariantOnlyMatchesItsOwnAdapters(): void
    {
        $factory = new BookstoreFactory([$this->adaptersFor(Variant::OrmNaive)], new ImmediateTransaction());

        $this->expectException(\LogicException::class);

        $factory->for(Variant::OrmOptimized);
    }

    public function testForVariantBuildsABookstoreWiredOnItsAdapters(): void
    {
        $catalogue = $this->createStub(Catalogue::class);
        $factory = new BookstoreFactory(
            [$this->adaptersFor(Variant::OrmNaive), $this->adaptersFor(Variant::Dbal, $catalogue)],
            new ImmediateTransaction(),
        );

        $bookstore = $factory->for(Variant::Dbal);

        self::assertSame($catalogue, $bookstore->catalogue);
        $purchase = ($bookstore->purchaseBook)(1, new \DateTimeImmutable('2026-06-15'));
        self::assertSame(1500, $purchase->paidInCents());
    }

    private function adaptersFor(Variant $variant, ?Catalogue $catalogue = null): VariantAdapters
    {
        return new class($variant, new InMemoryShelf([1 => 3]), new InMemoryPricing([1 => new Quote(2000, 5, 1500)]), $catalogue ?? new class implements Catalogue {
            public function export(\DateTimeImmutable $on): iterable
            {
                return [];
            }

            public function page(int $page, int $perPage, \DateTimeImmutable $on): array
            {
                return [];
            }
        }, new InMemoryPurchases(), new InMemoryRentals()) implements VariantAdapters {
            public function __construct(
                private readonly Variant $variant,
                private readonly Shelf $shelf,
                private readonly Pricing $pricing,
                private readonly Catalogue $catalogue,
                private readonly PurchaseRepository $purchases,
                private readonly RentalRepository $rentals,
            ) {
            }

            public function variant(): Variant
            {
                return $this->variant;
            }

            public function shelf(): Shelf
            {
                return $this->shelf;
            }

            public function pricing(): Pricing
            {
                return $this->pricing;
            }

            public function catalogue(): Catalogue
            {
                return $this->catalogue;
            }

            public function purchases(): PurchaseRepository
            {
                return $this->purchases;
            }

            public function rentals(): RentalRepository
            {
                return $this->rentals;
            }
        };
    }
}
