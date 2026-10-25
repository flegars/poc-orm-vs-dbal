<?php

namespace App\Tests\Unit\Infrastructure\Benchmark;

use App\Application\Variant;
use App\Infrastructure\Benchmark\CellResult;
use App\Infrastructure\Benchmark\CellStatus;
use App\Infrastructure\Benchmark\Scenario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CellResultTest extends TestCase
{
    /**
     * @return iterable<string, array{CellResult}>
     */
    public static function results(): iterable
    {
        yield 'ok' => [new CellResult(Scenario::CatalogueExport, Variant::OrmOptimized, 10_000, CellStatus::Ok, 812.34, 3, 52_428_800)];
        yield 'timeout without measures' => [new CellResult(Scenario::CatalogueExport, Variant::OrmNaive, 100_000, CellStatus::Timeout)];
        yield 'failed with error' => [new CellResult(Scenario::Return, Variant::Dbal, 100, CellStatus::Failed, error: 'No open rental found')];
    }

    #[DataProvider('results')]
    public function testToArrayThenFromArrayRestoresTheSameResult(CellResult $result): void
    {
        $restored = CellResult::fromArray(json_decode(json_encode($result->toArray(), \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR));

        self::assertEquals($result, $restored);
    }

    public function testTimeoutResultCarriesNoMeasures(): void
    {
        $restored = CellResult::fromArray((new CellResult(Scenario::Purchase, Variant::Dbal, 100, CellStatus::Timeout))->toArray());

        self::assertSame(CellStatus::Timeout, $restored->status);
        self::assertNull($restored->medianMs);
        self::assertNull($restored->queries);
        self::assertNull($restored->peakBytes);
    }
}
