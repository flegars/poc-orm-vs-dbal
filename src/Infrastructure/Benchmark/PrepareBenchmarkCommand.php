<?php

namespace App\Infrastructure\Benchmark;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

#[AsCommand('app:benchmark:prepare', 'Creates and seeds one benchmark database per catalogue size')]
final readonly class PrepareBenchmarkCommand
{
    public function __construct(
        private BenchmarkDatabases $databases,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Comma-separated catalogue sizes')]
        string $sizes = BenchmarkSizes::DEFAULT,
    ): int {
        try {
            $parsedSizes = BenchmarkSizes::parse($sizes);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }

        foreach ($parsedSizes as $size) {
            $env = ['DATABASE_URL' => $this->databases->urlFor($size)];
            $steps = [
                ['doctrine:database:create', '--if-not-exists'],
                ['doctrine:migrations:migrate', '-n'],
                ['app:catalogue:seed', \sprintf('--size=%d', $size)],
            ];

            foreach ($steps as $step) {
                $io->writeln(\sprintf('[%s] %s', $this->databases->nameFor($size), implode(' ', $step)));
                if (!$this->runStep($io, $step, $env)) {
                    return Command::FAILURE;
                }
            }
        }

        $io->writeln('cache:warmup (prod)');
        if (!$this->runStep($io, ['cache:warmup'], [])) {
            return Command::FAILURE;
        }

        $io->success(\sprintf('Benchmark databases ready for sizes: %s.', implode(', ', $parsedSizes)));

        return Command::SUCCESS;
    }

    /**
     * @param list<string>          $arguments
     * @param array<string, string> $env
     */
    private function runStep(SymfonyStyle $io, array $arguments, array $env): bool
    {
        $process = new Process(
            [\PHP_BINARY, 'bin/console', ...$arguments, '--no-interaction'],
            $this->projectDir,
            $env + ['APP_ENV' => 'prod', 'APP_DEBUG' => '0'],
            null,
            null,
        );
        $process->run();

        if ($process->isSuccessful()) {
            return true;
        }

        $io->error(\sprintf('Step "%s" failed (exit code %d).', implode(' ', $arguments), (int) $process->getExitCode()));
        $io->writeln(trim($process->getOutput()."\n".$process->getErrorOutput()));

        return false;
    }
}
