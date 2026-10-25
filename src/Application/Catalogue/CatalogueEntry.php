<?php

namespace App\Application\Catalogue;

use App\Domain\Book\Genre;

final readonly class CatalogueEntry
{
    /**
     * @param list<string> $authorNames
     */
    public function __construct(
        public int $bookId,
        public string $title,
        public array $authorNames,
        public Genre $genre,
        public int $basePriceInCents,
        public ?int $discountId,
        public int $finalPriceInCents,
    ) {
    }
}
