<?php

namespace App\Infrastructure\Benchmark;

enum Scenario: string
{
    case CatalogueExport = 'catalogue-export';
    case CataloguePage = 'catalogue-page';
    case Purchase = 'purchase';
    case Rental = 'rental';
    case Return = 'return';

    public function isWrite(): bool
    {
        return match ($this) {
            self::Purchase, self::Rental, self::Return => true,
            self::CatalogueExport, self::CataloguePage => false,
        };
    }
}
