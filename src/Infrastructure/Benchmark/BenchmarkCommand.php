<?php

namespace App\Infrastructure\Benchmark;

use App\Application\Variant;
use App\Infrastructure\Seeding\CatalogueSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:benchmark', 'Runs every scenario x variant x size cell and prints the result tables')]
final readonly class BenchmarkCommand
{
    public function __construct(
        private BenchmarkDatabases $databases,
        private CellRunner $runner,
        private BenchmarkTable $table,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Comma-separated catalogue sizes')]
        string $sizes = BenchmarkSizes::DEFAULT,
        #[Option(description: 'Comma-separated scenarios (default: all)')]
        string $scenarios = '',
        #[Option(description: 'Comma-separated variants (default: all)')]
        string $variants = '',
        #[Option(description: 'Measured runs per cell')]
        int $runs = 5,
        #[Option(description: 'Timeout per cell in seconds')]
        float $timeout = 120.0,
    ): int {
        try {
            $parsedSizes = BenchmarkSizes::parse($sizes);
            $parsedScenarios = $this->parseEnums(Scenario::class, $scenarios, 'scenario');
            $parsedVariants = $this->parseEnums(Variant::class, $variants, 'variant');
            if ($runs < 1) {
                throw new \InvalidArgumentException('--runs must be a positive integer.');
            }
            if ($timeout <= 0) {
                throw new \InvalidArgumentException('--timeout must be a positive number of seconds.');
            }
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        foreach ($parsedSizes as $size) {
            $books = $this->databases->countBooks($size);
            if (null === $books || 0 === $books) {
                $io->error(\sprintf('Database "%s" is missing or empty. Run "bin/console app:benchmark:prepare --sizes=%d" first.', $this->databases->nameFor($size), $size));

                return Command::FAILURE;
            }
        }

        $io->writeln(\sprintf('Reference date: %s | runs: %d | timeout: %s s', CatalogueSeeder::REFERENCE_DATE, $runs, rtrim(rtrim(number_format($timeout, 2, '.', ''), '0'), '.')));

        $progressOutput = $io->getErrorStyle();
        $bar = $progressOutput->createProgressBar(\count($parsedSizes) * \count($parsedScenarios) * \count($parsedVariants));
        $bar->setFormat(' %current%/%max% [%bar%] %message%');
        $bar->setMessage('');
        $bar->start();

        $results = [];
        foreach ($parsedSizes as $size) {
            foreach ($parsedScenarios as $scenario) {
                foreach ($parsedVariants as $variant) {
                    $bar->setMessage(\sprintf('%s / %s / %d', $scenario->value, $variant->value, $size));
                    $bar->display();
                    $results[] = $this->runner->run($scenario, $variant, $size, $runs, $timeout);
                    $bar->advance();
                }
            }
        }
        $bar->finish();
        $progressOutput->newLine(2);

        $this->table->render($io, $results);

        $failures = array_filter($results, static fn (CellResult $r): bool => CellStatus::Failed === $r->status);
        foreach ($failures as $failure) {
            $io->writeln(\sprintf('<error>failed: %s / %s / %d</error> %s', $failure->scenario->value, $failure->variant->value, $failure->size, $failure->error));
        }

        return Command::SUCCESS;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return list<T>
     */
    private function parseEnums(string $enum, string $value, string $label): array
    {
        if ('' === trim($value)) {
            return $enum::cases();
        }

        $items = [];
        foreach (explode(',', $value) as $part) {
            $item = $enum::tryFrom(trim($part));
            if (null === $item) {
                throw new \InvalidArgumentException(\sprintf('Unknown %s "%s". Valid values: %s.', $label, trim($part), implode(', ', array_map(static fn (\BackedEnum $c): string => (string) $c->value, $enum::cases()))));
            }
            $items[] = $item;
        }

        return $items;
    }
}
