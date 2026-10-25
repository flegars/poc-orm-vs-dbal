<?php

namespace App\Infrastructure\Benchmark;

use App\Application\Variant;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Output\OutputInterface;

final class BenchmarkTable
{
    private const float BYTES_PER_MB = 1_048_576;

    /**
     * @param list<CellResult> $results
     */
    public function render(OutputInterface $output, array $results): void
    {
        $variants = array_values(array_filter(Variant::cases(), static fn (Variant $v): bool => array_any($results, static fn (CellResult $r): bool => $r->variant === $v)));

        foreach (Scenario::cases() as $scenario) {
            $scenarioResults = array_filter($results, static fn (CellResult $r): bool => $r->scenario === $scenario);
            if ([] === $scenarioResults) {
                continue;
            }

            $sizes = array_unique(array_map(static fn (CellResult $r): int => $r->size, $scenarioResults));
            sort($sizes);

            $groups = [''];
            $metrics = ['size'];
            foreach ($variants as $variant) {
                $groups[] = new TableCell($variant->value, ['colspan' => 3]);
                array_push($metrics, 'ms', 'queries', 'peak RSS MB');
            }

            $rows = [];
            foreach ($sizes as $size) {
                $row = [number_format($size, 0, '', ' ')];
                foreach ($variants as $variant) {
                    array_push($row, ...$this->cells($this->find($scenarioResults, $variant, $size)));
                }
                $rows[] = $row;
            }

            $output->writeln(\sprintf('<info>%s</info>', $scenario->value));
            (new Table($output))
                ->setHeaders([$groups, $metrics])
                ->setRows($rows)
                ->render();
            $output->writeln('');
        }
    }

    /**
     * @param array<CellResult> $results
     */
    private function find(array $results, Variant $variant, int $size): ?CellResult
    {
        foreach ($results as $result) {
            if ($result->variant === $variant && $result->size === $size) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function cells(?CellResult $result): array
    {
        if (null === $result) {
            return ['-', '-', '-'];
        }

        return match ($result->status) {
            CellStatus::Timeout => ['timeout', 'timeout', 'timeout'],
            CellStatus::Failed => ['failed', 'failed', 'failed'],
            CellStatus::Ok => [
                number_format($result->medianMs ?? 0.0, 1, '.', ''),
                (string) ($result->queries ?? 0),
                number_format(($result->peakBytes ?? 0) / self::BYTES_PER_MB, 1, '.', ''),
            ],
        };
    }
}
