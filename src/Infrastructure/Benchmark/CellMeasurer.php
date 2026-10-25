<?php

namespace App\Infrastructure\Benchmark;

use App\Application\Bookstore;
use App\Application\BookstoreFactory;
use App\Application\Variant;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class CellMeasurer
{
    private const int PER_PAGE = 50;

    public function __construct(
        private BookstoreFactory $factory,
        private EntityManagerInterface $entityManager,
        private Connection $connection,
        private QueryCounter $counter,
    ) {
    }

    public function measure(Scenario $scenario, Variant $variant, int $size, int $runs, \DateTimeImmutable $on): CellResult
    {
        try {
            $bookstore = $this->factory->for($variant);
            $operation = $this->operationFor($scenario, $bookstore, $on);

            gc_collect_cycles();
            $rssBefore = self::peakResidentBytes();

            $this->run($scenario, $operation);

            $durations = [];
            $queries = 0;
            for ($run = 0; $run < $runs; ++$run) {
                $sample = $this->run($scenario, $operation);
                $durations[] = $sample['milliseconds'];
                $queries = $sample['queries'];
            }
            $peak = max(0, self::peakResidentBytes() - $rssBefore);
        } catch (\Throwable $exception) {
            return new CellResult($scenario, $variant, $size, CellStatus::Failed, error: $exception->getMessage());
        }

        return new CellResult($scenario, $variant, $size, CellStatus::Ok, self::median($durations), $queries, $peak);
    }

    /**
     * @return \Closure(): mixed
     */
    private function operationFor(Scenario $scenario, Bookstore $bookstore, \DateTimeImmutable $on): \Closure
    {
        return match ($scenario) {
            Scenario::CatalogueExport => static fn (): int => iterator_count($bookstore->catalogue->export($on)),
            Scenario::CataloguePage => $this->pageOperation($bookstore, $on),
            Scenario::Purchase => $this->bookOperation($bookstore->purchaseBook, $on),
            Scenario::Rental => $this->bookOperation($bookstore->rentBook, $on),
            Scenario::Return => $this->returnOperation($bookstore, $on),
        };
    }

    private function pageOperation(Bookstore $bookstore, \DateTimeImmutable $on): \Closure
    {
        $bookCount = (int) $this->connection->fetchOne('SELECT count(*) FROM book');
        $page = intdiv(intdiv($bookCount, self::PER_PAGE), 2) + 1;

        return static fn (): array => $bookstore->catalogue->page($page, self::PER_PAGE, $on);
    }

    private function bookOperation(callable $useCase, \DateTimeImmutable $on): \Closure
    {
        $bookId = $this->connection->fetchOne('SELECT id FROM book WHERE stock > 0 ORDER BY id LIMIT 1');
        if (false === $bookId) {
            throw new \RuntimeException('No book in stock to measure against.');
        }

        return static fn (): mixed => $useCase((int) $bookId, $on);
    }

    private function returnOperation(Bookstore $bookstore, \DateTimeImmutable $on): \Closure
    {
        $rentalId = $this->connection->fetchOne('SELECT id FROM rental WHERE returned_on IS NULL ORDER BY id LIMIT 1');
        if (false === $rentalId) {
            throw new \RuntimeException('No open rental to measure against.');
        }
        $id = Uuid::fromString((string) $rentalId);

        return static fn (): mixed => ($bookstore->returnRental)($id, $on);
    }

    /**
     * @param \Closure(): mixed $operation
     *
     * @return array{milliseconds: float, queries: int}
     */
    private function run(Scenario $scenario, \Closure $operation): array
    {
        $this->entityManager->clear();
        gc_collect_cycles();

        if ($scenario->isWrite()) {
            $this->connection->beginTransaction();
        }

        try {
            $this->counter->reset();
            $start = hrtime(true);
            $operation();
            $elapsed = hrtime(true) - $start;
            $queries = $this->counter->count();
        } finally {
            if ($scenario->isWrite()) {
                $this->connection->rollBack();
            }
        }

        return ['milliseconds' => $elapsed / 1_000_000, 'queries' => $queries];
    }

    private static function peakResidentBytes(): int
    {
        // libpq buffers whole result sets outside the PHP heap, so only the process RSS sees them.
        $maxRss = getrusage()['ru_maxrss'];

        return 'Darwin' === PHP_OS_FAMILY ? $maxRss : $maxRss * 1024;
    }

    /**
     * @param non-empty-list<float> $values
     */
    private static function median(array $values): float
    {
        sort($values);
        $middle = intdiv(\count($values), 2);

        return 1 === \count($values) % 2 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }
}
