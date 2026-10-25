<?php

namespace App\Infrastructure\Benchmark;

use App\Application\Variant;
use App\Infrastructure\Seeding\CatalogueSeeder;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('app:benchmark:cell', 'Measures one benchmark cell and prints the result as JSON')]
final readonly class RunCellCommand
{
    public function __construct(private CellMeasurer $measurer)
    {
    }

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Scenario')]
        Scenario $scenario,
        #[Argument(description: 'Variant')]
        Variant $variant,
        #[Argument(description: 'Catalogue size, echoed in the result')]
        int $size,
        #[Option(description: 'Number of measured runs')]
        int $runs = 5,
    ): int {
        $result = $this->measurer->measure($scenario, $variant, $size, $runs, new \DateTimeImmutable(CatalogueSeeder::REFERENCE_DATE));

        $output->writeln(json_encode($result->toArray(), \JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
