<?php

namespace App\Tests\Integration\Benchmark;

use App\Application\Variant;
use App\Infrastructure\Benchmark\CellRunner;
use App\Infrastructure\Benchmark\CellStatus;
use App\Infrastructure\Benchmark\Scenario;
use App\Tests\Integration\Persistence\DatabaseTestCase;
use App\Tests\Integration\Persistence\ReferenceCatalogue;

final class CellRunnerTest extends DatabaseTestCase
{
    private CellRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        ReferenceCatalogue::load($this->connection());
        $this->runner = self::getContainer()->get(CellRunner::class);
    }

    public function testRunnerReportsATimeout(): void
    {
        $result = $this->runner->run(Scenario::CataloguePage, Variant::Dbal, 4, 1, 0.05, $this->testDatabaseUrl());

        self::assertSame(CellStatus::Timeout, $result->status);
        self::assertSame(Scenario::CataloguePage, $result->scenario);
        self::assertSame(Variant::Dbal, $result->variant);
        self::assertSame(4, $result->size);
    }

    public function testRunnerReturnsTheChildResult(): void
    {
        $result = $this->runner->run(Scenario::CataloguePage, Variant::Dbal, 4, 1, 60.0, $this->testDatabaseUrl());

        self::assertSame(CellStatus::Ok, $result->status, (string) $result->error);
        self::assertSame(Scenario::CataloguePage, $result->scenario);
        self::assertSame(Variant::Dbal, $result->variant);
        self::assertSame(4, $result->size);
        self::assertSame(1, $result->queries);
        self::assertGreaterThan(0, $result->medianMs);
    }

    public function testRunnerReportsFailureWhenTheDatabaseDoesNotExist(): void
    {
        $url = $this->testDatabaseUrl('app_bench_missing_database');

        $result = $this->runner->run(Scenario::CataloguePage, Variant::Dbal, 4, 1, 60.0, $url);

        self::assertSame(CellStatus::Failed, $result->status);
        self::assertNotSame('', (string) $result->error);
        self::assertSame(Scenario::CataloguePage, $result->scenario);
    }

    private function testDatabaseUrl(?string $databaseName = null): string
    {
        $params = $this->connection()->getParams();

        return \sprintf(
            'postgresql://%s:%s@%s:%d/%s?serverVersion=18&charset=utf8',
            $params['user'],
            $params['password'],
            $params['host'],
            $params['port'] ?? 5432,
            $databaseName ?? $params['dbname'],
        );
    }
}
