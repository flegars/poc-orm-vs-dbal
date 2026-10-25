<?php

namespace App\Tests\Integration\Persistence;

use App\Application\Catalogue\CatalogueEntry;
use App\Application\Variant;
use App\Domain\Book\Genre;
use PHPUnit\Framework\Attributes\DataProvider;

final class CatalogueAdaptersTest extends DatabaseTestCase
{
    use ImplementedVariants;

    #[DataProvider('variants')]
    public function testExportMatchesTheReferenceCatalogue(Variant $variant): void
    {
        ReferenceCatalogue::load($this->connection());

        $entries = iterator_to_array($this->bookstore($variant)->catalogue->export($this->on()), false);

        self::assertEquals(self::expectedEntries(), $entries);
    }

    #[DataProvider('variants')]
    public function testPageReturnsTheRequestedSlice(Variant $variant): void
    {
        ReferenceCatalogue::load($this->connection());
        $catalogue = $this->bookstore($variant)->catalogue;

        self::assertEquals([self::expectedEntries()[2], self::expectedEntries()[3]], $catalogue->page(2, 2, $this->on()));
        self::assertEquals([self::expectedEntries()[3]], $catalogue->page(2, 3, $this->on()));
    }

    #[DataProvider('variants')]
    public function testPageBeyondTheLastPageIsEmpty(Variant $variant): void
    {
        ReferenceCatalogue::load($this->connection());

        self::assertSame([], $this->bookstore($variant)->catalogue->page(3, 2, $this->on()));
    }

    /**
     * @return list<CatalogueEntry>
     */
    private static function expectedEntries(): array
    {
        return [
            new CatalogueEntry(1, 'Alpha', ['Ada Martin'], Genre::Fantasy, 2000, 5, 1500),
            new CatalogueEntry(2, 'Bravo', ['Bruno Petit'], Genre::ScienceFiction, 1000, 2, 500),
            new CatalogueEntry(3, 'Charlie', ['Ada Martin', 'Bruno Petit'], Genre::Fantasy, 400, 2, 0),
            new CatalogueEntry(4, 'Delta', ['Chloé Durand'], Genre::History, 5000, null, 5000),
        ];
    }
}
