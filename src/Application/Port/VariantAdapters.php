<?php

namespace App\Application\Port;

use App\Application\Variant;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface VariantAdapters
{
    public function variant(): Variant;

    public function shelf(): Shelf;

    public function pricing(): Pricing;

    public function catalogue(): Catalogue;

    public function purchases(): PurchaseRepository;

    public function rentals(): RentalRepository;
}
