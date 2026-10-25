<?php

namespace App\Infrastructure\Seeding;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('app:catalogue:seed', 'Seeds a reproducible bookstore catalogue')]
final readonly class SeedCatalogueCommand
{
    public function __construct(private CatalogueSeeder $seeder, private Connection $connection)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Number of books to seed')]
        ?int $size = null,
        #[Option(description: 'Random seed')]
        int $seed = 42,
    ): int {
        if (null === $size || $size < 1) {
            $io->error('The --size option is required and must be a positive integer.');

            return Command::INVALID;
        }

        $this->seeder->seed($size, $seed);

        $rows = [];
        foreach (CatalogueSeeder::TABLES as $table) {
            $rows[] = [$table, (int) $this->connection->fetchOne(\sprintf('SELECT count(*) FROM %s', $table))];
        }
        $io->table(['Table', 'Rows'], $rows);

        return Command::SUCCESS;
    }
}
