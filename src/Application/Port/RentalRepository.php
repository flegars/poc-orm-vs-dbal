<?php

namespace App\Application\Port;

use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalNotFound;
use Symfony\Component\Uid\Uuid;

interface RentalRepository
{
    /**
     * @throws RentalNotFound
     */
    public function get(Uuid $id): Rental;

    public function save(Rental $rental): void;
}
