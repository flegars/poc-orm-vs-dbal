<?php

namespace App\Tests\Integration\Benchmark;

use App\Application\Variant;
use App\Infrastructure\Benchmark\CellMeasurer;
use App\Infrastructure\Benchmark\CellResult;
use App\Infrastructure\Benchmark\CellStatus;
use App\Infrastructure\Benchmark\Scenario;
use App\Tests\Integration\Persistence\DatabaseTestCase;
use App\Tests\Integration\Persistence\ImplementedVariants;
use App\Tests\Integration\Persistence\ReferenceCatalogue;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class CellMeasurerTest extends DatabaseTestCase
{
    use ImplementedVariants;

    private CellMeasurer $measurer;

    protected function setUp(): void
    {
        parent::setUp();
        ReferenceCatalogue::load($this->connection());
        $this->measurer = self::getContainer()->get(CellMeasurer::class);
    }

    public function testDbalPageIsASingleQuery(): void
    {
        $result = $this->measurer->measure(Scenario::CataloguePage, Variant::Dbal, 4, 2, $this->on());

        self::assertSame(CellStatus::Ok, $result->status);
        self::assertSame(1, $result->queries);
        self::assertGreaterThan(0, $result->medianMs);
    }

    public function testNaiveExportIssuesMoreQueriesThanDbal(): void
    {
        $naive = $this->measurer->measure(Scenario::CatalogueExport, Variant::OrmNaive, 4, 1, $this->on());
        $dbal = $this->measurer->measure(Scenario::CatalogueExport, Variant::Dbal, 4, 1, $this->on());

        self::assertSame(CellStatus::Ok, $naive->status);
        self::assertSame(CellStatus::Ok, $dbal->status);
        self::assertGreaterThan($dbal->queries, $naive->queries);
    }

    public function testWriteScenariosLeaveTheDataUntouched(): void
    {
        foreach ([Scenario::Purchase, Scenario::Rental, Scenario::Return] as $scenario) {
            foreach (Variant::cases() as $variant) {
                $result = $this->measurer->measure($scenario, $variant, 4, 2, $this->on());
                self::assertSame(CellStatus::Ok, $result->status, \sprintf('%s/%s: %s', $scenario->value, $variant->value, $result->error));
            }
        }

        self::assertSame([3, 0, 1, 2], array_map($this->stockOf(...), [1, 2, 3, 4]));
        self::assertSame(0, $this->rowCount('purchase'));
        self::assertSame(1, $this->rowCount('rental'));
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM rental WHERE returned_on IS NULL'));
        self::assertFalse($this->connection()->isTransactionActive());
    }

    public function testEveryCellSucceedsOnTheReferenceCatalogue(): void
    {
        foreach (Scenario::cases() as $scenario) {
            foreach (Variant::cases() as $variant) {
                $result = $this->measurer->measure($scenario, $variant, 4, 1, $this->on());
                $label = \sprintf('%s/%s', $scenario->value, $variant->value);

                self::assertSame(CellStatus::Ok, $result->status, \sprintf('%s: %s', $label, $result->error));
                self::assertGreaterThanOrEqual(1, $result->queries, $label);
                self::assertGreaterThanOrEqual(0, $result->peakBytes, $label);
            }
        }
    }

    #[DataProvider('variants')]
    public function testQueryCountsPerVariantOnTheExport(Variant $variant): void
    {
        $result = $this->measurer->measure(Scenario::CatalogueExport, $variant, 4, 1, $this->on());

        self::assertSame(CellStatus::Ok, $result->status);
        self::assertGreaterThanOrEqual(1, $result->queries);
    }

    public function testCellCommandPrintsJson(): void
    {
        $command = (new Application(self::$kernel))->find('app:benchmark:cell');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['scenario' => 'catalogue-page', 'variant' => 'dbal', 'size' => '4', '--runs' => '1']);

        self::assertSame(0, $exitCode);
        $result = CellResult::fromArray(json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR));
        self::assertSame(CellStatus::Ok, $result->status);
        self::assertSame(Scenario::CataloguePage, $result->scenario);
        self::assertSame(Variant::Dbal, $result->variant);
        self::assertSame(4, $result->size);
    }
}
