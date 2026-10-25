<?php

namespace App\Infrastructure\Persistence\Dbal;

use App\Application\Port\Catalogue;
use App\Application\Port\Pricing;
use App\Application\Port\PurchaseRepository;
use App\Application\Port\RentalRepository;
use App\Application\Port\Shelf;
use App\Application\Port\VariantAdapters;
use App\Application\Variant;

final readonly class DbalAdapters implements VariantAdapters
{
    public function __construct(
        private DbalShelf $shelf,
        private DbalPricing $pricing,
        private DbalCatalogue $catalogue,
        private DbalPurchaseRepository $purchases,
        private DbalRentalRepository $rentals,
    ) {
    }

    public function variant(): Variant
    {
        return Variant::Dbal;
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
