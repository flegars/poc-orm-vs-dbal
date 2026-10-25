<?php

namespace App\Tests\Integration\Seeding;

use App\Application\Port\Pricing;
use App\Application\Variant;
use App\Domain\Discount\PriceBasis;
use App\Infrastructure\Persistence\Dbal\DbalPricing;
use App\Infrastructure\Persistence\OrmNaive\OrmNaivePricing;
use App\Infrastructure\Persistence\OrmOptimized\OrmOptimizedPricing;
use App\Infrastructure\Seeding\CatalogueSeeder;
use App\Tests\Integration\Persistence\DatabaseTestCase;
use App\Tests\Integration\Persistence\ImplementedVariants;
use PHPUnit\Framework\Attributes\DataProvider;

final class SeededEquivalenceTest extends DatabaseTestCase
{
    use ImplementedVariants;

    private const BOOKS = 300;

    public function testAllVariantsAgreeOnASeededCatalogue(): void
    {
        $this->seed();
        $on = $this->on();
        $reference = iterator_to_array($this->bookstore(Variant::Dbal)->catalogue->export($on), false);
        self::assertCount(self::BOOKS, $reference);

        foreach ([Variant::OrmNaive, Variant::OrmOptimized] as $variant) {
            $this->entityManager()->clear();
            self::assertEquals($reference, iterator_to_array($this->bookstore($variant)->catalogue->export($on), false), $variant->value);
        }
    }

    public function testExportsAgreeAcrossBatchBoundaries(): void
    {
        $this->seed(2500);
        $on = $this->on();
        $reference = iterator_to_array($this->bookstore(Variant::Dbal)->catalogue->export($on), false);
        self::assertCount(2500, $reference);

        foreach ([Variant::OrmNaive, Variant::OrmOptimized] as $variant) {
            $this->entityManager()->clear();
            $position = 0;
            foreach ($this->bookstore($variant)->catalogue->export($on) as $entry) {
                self::assertEquals($reference[$position], $entry, \sprintf('%s, position %d', $variant->value, $position));
                ++$position;
            }
            self::assertSame(2500, $position, $variant->value);
        }
    }

    #[DataProvider('variants')]
    public function testPageThreeMatchesTheExportSliceForEveryVariant(Variant $variant): void
    {
        $this->seed();
        $on = $this->on();
        $export = iterator_to_array($this->bookstore($variant)->catalogue->export($on), false);
        $this->entityManager()->clear();

        self::assertEquals(
            \array_slice($export, 100, 50),
            $this->bookstore($variant)->catalogue->page(3, 50, $on),
            $variant->value,
        );
    }

    public function testAllVariantsQuoteTheSameForEveryBook(): void
    {
        $this->seed();
        $on = $this->on();
        $bookIds = array_map('intval', $this->connection()->fetchFirstColumn('SELECT id FROM book ORDER BY id LIMIT 50'));
        self::assertCount(50, $bookIds);

        $pricings = $this->pricings();
        foreach (PriceBasis::cases() as $basis) {
            foreach ($bookIds as $bookId) {
                $reference = $pricings[Variant::Dbal->value]->quote($bookId, $basis, $on);
                foreach ([Variant::OrmNaive, Variant::OrmOptimized] as $variant) {
                    $this->entityManager()->clear();
                    self::assertEquals(
                        $reference,
                        $pricings[$variant->value]->quote($bookId, $basis, $on),
                        \sprintf('%s, book %d, %s', $variant->value, $bookId, $basis->name),
                    );
                }
            }
        }
    }

    public function testSeedingIsReproducible(): void
    {
        $this->seed();
        $books = $this->connection()->fetchAllAssociative('SELECT * FROM book ORDER BY id');
        $discounts = $this->connection()->fetchAllAssociative('SELECT * FROM discount ORDER BY id');
        self::assertCount(self::BOOKS, $books);

        $this->seed();

        self::assertSame($books, $this->connection()->fetchAllAssociative('SELECT * FROM book ORDER BY id'));
        self::assertSame($discounts, $this->connection()->fetchAllAssociative('SELECT * FROM discount ORDER BY id'));
    }

    public function testPurchaseWritesTheSameRowAndStockForEveryVariant(): void
    {
        $this->seed();
        $bookId = (int) $this->connection()->fetchOne('SELECT id FROM book WHERE stock > 0 ORDER BY id LIMIT 1');
        $initialStock = $this->stockOf($bookId);

        $outcomes = [];
        foreach (Variant::cases() as $variant) {
            $this->entityManager()->clear();
            $this->connection()->beginTransaction();
            try {
                $purchase = ($this->bookstore($variant)->purchaseBook)($bookId, $this->on());
                $outcomes[$variant->value] = [
                    $purchase->basePriceInCents(),
                    $purchase->discountId(),
                    $purchase->paidInCents(),
                    $this->stockOf($bookId),
                    $this->connection()->fetchAssociative(
                        'SELECT book_id, purchased_on, base_price_in_cents, discount_id, paid_in_cents FROM purchase WHERE id = ?',
                        [$purchase->id()->toRfc4122()],
                    ),
                ];
            } finally {
                $this->connection()->rollBack();
            }
        }

        $reference = $outcomes[Variant::Dbal->value];
        self::assertSame($initialStock - 1, $reference[3]);
        foreach ($outcomes as $variant => $outcome) {
            self::assertEquals($reference, $outcome, $variant);
        }
        self::assertSame($initialStock, $this->stockOf($bookId));
    }

    private function seed(int $books = self::BOOKS): void
    {
        self::getContainer()->get(CatalogueSeeder::class)->seed($books);
        $this->entityManager()->clear();
    }

    /**
     * @return array<string, Pricing>
     */
    private function pricings(): array
    {
        $container = self::getContainer();

        return [
            Variant::Dbal->value => $container->get(DbalPricing::class),
            Variant::OrmNaive->value => $container->get(OrmNaivePricing::class),
            Variant::OrmOptimized->value => $container->get(OrmOptimizedPricing::class),
        ];
    }
}
