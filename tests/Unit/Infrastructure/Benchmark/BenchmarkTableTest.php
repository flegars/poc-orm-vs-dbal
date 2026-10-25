<?php

namespace App\Tests\Unit\Infrastructure\Benchmark;

use App\Application\Variant;
use App\Infrastructure\Benchmark\BenchmarkTable;
use App\Infrastructure\Benchmark\CellResult;
use App\Infrastructure\Benchmark\CellStatus;
use App\Infrastructure\Benchmark\Scenario;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class BenchmarkTableTest extends TestCase
{
    public function testTableShowsTimeoutCells(): void
    {
        $display = $this->render([
            new CellResult(Scenario::CatalogueExport, Variant::Dbal, 100000, CellStatus::Ok, 812.34, 1, 52_428_800),
            new CellResult(Scenario::CatalogueExport, Variant::OrmNaive, 100000, CellStatus::Timeout),
        ]);

        self::assertStringContainsString('812.3', $display);
        self::assertStringContainsString('timeout', $display);
        self::assertStringContainsString('50.0', $display);
        self::assertMatchesRegularExpression('/100[ \x{00A0}\x{202F},.]000/u', $display);
        self::assertDoesNotMatchRegularExpression('/(?<![\d ,.\x{00A0}\x{202F}])100000(?!\d)/u', $display);
    }

    public function testTableShowsFailedCells(): void
    {
        $display = $this->render([
            new CellResult(Scenario::Rental, Variant::OrmOptimized, 100, CellStatus::Failed, error: 'SQLSTATE[08006] connection refused'),
            new CellResult(Scenario::Rental, Variant::Dbal, 100, CellStatus::Ok, 3.21, 4, 1_048_576),
        ]);

        self::assertStringContainsString('failed', $display);
        self::assertStringContainsString('3.2', $display);
        self::assertStringNotContainsString('timeout', $display);
    }

    public function testTableOrdersVariantColumnsLikeVariantCases(): void
    {
        $display = $this->render([
            new CellResult(Scenario::CataloguePage, Variant::Dbal, 10000, CellStatus::Ok, 1.5, 1, 2_097_152),
            new CellResult(Scenario::CataloguePage, Variant::OrmOptimized, 10000, CellStatus::Ok, 2.5, 2, 4_194_304),
            new CellResult(Scenario::CataloguePage, Variant::OrmNaive, 10000, CellStatus::Ok, 9.5, 52, 8_388_608),
        ]);

        $positions = array_map(static fn (Variant $variant): int|false => strpos($display, $variant->value), Variant::cases());

        self::assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        self::assertSame($sorted, $positions);
        self::assertSame(['orm-naive', 'orm-optimized', 'dbal'], array_map(static fn (Variant $variant): string => $variant->value, Variant::cases()));
    }

    public function testTableRendersOneTablePerPresentScenario(): void
    {
        $display = $this->render([
            new CellResult(Scenario::Purchase, Variant::Dbal, 100, CellStatus::Ok, 1.1, 3, 1_048_576),
            new CellResult(Scenario::Return, Variant::OrmNaive, 100, CellStatus::Ok, 1.2, 3, 1_048_576),
            new CellResult(Scenario::Purchase, Variant::OrmNaive, 100, CellStatus::Ok, 2.1, 6, 2_097_152),
        ]);

        self::assertStringContainsString(Scenario::Purchase->value, $display);
        self::assertStringContainsString(Scenario::Return->value, $display);
        self::assertStringNotContainsString(Scenario::CatalogueExport->value, $display);
        self::assertStringNotContainsString(Scenario::CataloguePage->value, $display);
        self::assertStringNotContainsString(Scenario::Rental->value, $display);
        self::assertSame(2, substr_count($display, Variant::OrmNaive->value));
    }

    /**
     * @param list<CellResult> $results
     */
    private function render(array $results): string
    {
        $output = new BufferedOutput();
        (new BenchmarkTable())->render($output, $results);

        return $output->fetch();
    }
}
