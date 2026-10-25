<?php

namespace App\Tests\Unit\Application\Fake;

use App\Application\Port\RentalRepository;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalNotFound;
use Symfony\Component\Uid\Uuid;

final class InMemoryRentals implements RentalRepository
{
    /** @var array<string, Rental> */
    public array $saved = [];

    public function get(Uuid $id): Rental
    {
        return $this->saved[$id->toRfc4122()] ?? throw RentalNotFound::withId($id);
    }

    public function save(Rental $rental): void
    {
        $this->saved[$rental->id()->toRfc4122()] = $rental;
    }
}
