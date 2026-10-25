<?php

namespace App\Tests\Unit\Application\Fake;

use App\Application\Port\PurchaseRepository;
use App\Domain\Sale\Purchase;

final class InMemoryPurchases implements PurchaseRepository
{
    /** @var list<Purchase> */
    public array $saved = [];

    public function save(Purchase $purchase): void
    {
        $this->saved[] = $purchase;
    }
}
