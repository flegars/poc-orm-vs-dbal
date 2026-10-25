<?php

namespace App\Application;

use App\Application\Port\Catalogue;
use App\Application\UseCase\PurchaseBook;
use App\Application\UseCase\RentBook;
use App\Application\UseCase\ReturnRental;

final readonly class Bookstore
{
    public function __construct(
        public PurchaseBook $purchaseBook,
        public RentBook $rentBook,
        public ReturnRental $returnRental,
        public Catalogue $catalogue,
    ) {
    }
}
