<?php

namespace App\Application\Port;

use App\Domain\Sale\Purchase;

interface PurchaseRepository
{
    public function save(Purchase $purchase): void;
}
