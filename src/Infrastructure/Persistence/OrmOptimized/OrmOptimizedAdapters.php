<?php

namespace App\Infrastructure\Persistence\OrmOptimized;

use App\Application\Port\Catalogue;
use App\Application\Port\Pricing;
use App\Application\Port\PurchaseRepository;
use App\Application\Port\RentalRepository;
use App\Application\Port\Shelf;
use App\Application\Port\VariantAdapters;
use App\Application\Variant;
use App\Infrastructure\Persistence\Orm\OrmPurchaseRepository;
use App\Infrastructure\Persistence\Orm\OrmRentalRepository;

final readonly class OrmOptimizedAdapters implements VariantAdapters
{
    public function __construct(
        private OrmOptimizedShelf $shelf,
        private OrmOptimizedPricing $pricing,
        private OrmOptimizedCatalogue $catalogue,
        private OrmPurchaseRepository $purchases,
        private OrmRentalRepository $rentals,
    ) {
    }

    public function variant(): Variant
    {
        return Variant::OrmOptimized;
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
}
