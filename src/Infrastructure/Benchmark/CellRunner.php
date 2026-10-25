<?php

namespace App\Infrastructure\Benchmark;

use App\Application\Variant;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final readonly class CellRunner
{
    private const int STDERR_TAIL_BYTES = 2000;

    public function __construct(
        private BenchmarkDatabases $databases,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function run(Scenario $scenario, Variant $variant, int $size, int $runs, float $timeoutSeconds, ?string $databaseUrl = null): CellResult
    {
        $process = new Process(
            [\PHP_BINARY, 'bin/console', 'app:benchmark:cell', $scenario->value, $variant->value, (string) $size, \sprintf('--runs=%d', $runs)],
            $this->projectDir,
            ['APP_ENV' => 'prod', 'APP_DEBUG' => '0', 'DATABASE_URL' => str_replace('%', '%%', $databaseUrl ?? $this->databases->urlFor($size))],
            null,
            $timeoutSeconds,
        );

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            return new CellResult($scenario, $variant, $size, CellStatus::Timeout);
        }

        if (0 !== $process->getExitCode()) {
            return new CellResult($scenario, $variant, $size, CellStatus::Failed, error: substr(trim($process->getErrorOutput() ?: $process->getOutput()), -self::STDERR_TAIL_BYTES));
        }

        try {
            $data = json_decode($process->getOutput(), true, 512, \JSON_THROW_ON_ERROR);

            return CellResult::fromArray($data);
        } catch (\Throwable $e) {
            return new CellResult($scenario, $variant, $size, CellStatus::Failed, error: 'Invalid cell output: '.$e->getMessage());
        }
    }
}
