<?php

namespace App\Tests\Unit\Application;

use App\Application\UseCase\ReturnRental;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalAlreadyReturned;
use App\Domain\Rental\RentalNotFound;
use App\Tests\Unit\Application\Fake\ImmediateTransaction;
use App\Tests\Unit\Application\Fake\InMemoryRentals;
use App\Tests\Unit\Application\Fake\InMemoryShelf;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ReturnRentalTest extends TestCase
{
    private InMemoryShelf $shelf;
    private InMemoryRentals $rentals;
    private ReturnRental $returnRental;

    protected function setUp(): void
    {
        $this->shelf = new InMemoryShelf([4 => 2]);
        $this->rentals = new InMemoryRentals();
        $this->returnRental = new ReturnRental($this->shelf, $this->rentals, new ImmediateTransaction());
    }

    public function testReturnPutsTheCopyBack(): void
    {
        $rental = $this->openRental();

        ($this->returnRental)($rental->id(), new \DateTimeImmutable('2026-06-20'));

        self::assertSame(3, $this->shelf->stockOf(4));
        self::assertSame('2026-06-20', $rental->returnedOn()?->format('Y-m-d'));
        self::assertTrue($this->rentals->get($rental->id())->isReturned());
    }

    public function testReturningAnAlreadyReturnedRentalLeavesStockUnchanged(): void
    {
        $rental = $this->openRental();
        $rental->markReturned(new \DateTimeImmutable('2026-06-18'));

        try {
            ($this->returnRental)($rental->id(), new \DateTimeImmutable('2026-06-20'));
            self::fail('Expected RentalAlreadyReturned.');
        } catch (RentalAlreadyReturned) {
        }

        self::assertSame(2, $this->shelf->stockOf(4));
        self::assertSame('2026-06-18', $rental->returnedOn()?->format('Y-m-d'));
    }

    public function testReturningAnUnknownRentalLeavesStockUnchanged(): void
    {
        try {
            ($this->returnRental)(Uuid::v7(), new \DateTimeImmutable('2026-06-20'));
            self::fail('Expected RentalNotFound.');
        } catch (RentalNotFound) {
        }

        self::assertSame(2, $this->shelf->stockOf(4));
        self::assertSame([], $this->rentals->saved);
    }

    private function openRental(): Rental
    {
        $rental = new Rental(Uuid::v7(), 4, new \DateTimeImmutable('2026-06-15'), new \DateTimeImmutable('2026-06-29'), 200, null, 200);
        $this->rentals->save($rental);

        return $rental;
    }
}
