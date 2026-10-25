<?php

namespace App\Application\Port;

use App\Application\Catalogue\CatalogueEntry;

interface Catalogue
{
    /**
     * @return iterable<CatalogueEntry>
     */
    public function export(\DateTimeImmutable $on): iterable;

    /**
     * @return list<CatalogueEntry>
     */
    public function page(int $page, int $perPage, \DateTimeImmutable $on): array;
}
