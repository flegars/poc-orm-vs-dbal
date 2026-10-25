<?php

namespace App\Infrastructure\Benchmark;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class BenchmarkDatabases
{
    public function __construct(
        #[Autowire('%env(DATABASE_URL)%')]
        private string $databaseUrl,
    ) {
    }

    public function urlFor(int $size): string
    {
        $url = preg_replace('#^([^:]+://[^/?]*)/[^?]*#', '${1}/'.$this->nameFor($size), $this->databaseUrl, 1, $count);
        if (null === $url || 0 === $count) {
            throw new \LogicException('DATABASE_URL must contain a host and a database name.');
        }

        return $url;
    }

    public function nameFor(int $size): string
    {
        return \sprintf('app_bench_%d', $size);
    }

    public function countBooks(int $size): ?int
    {
        $connection = $this->connect($this->urlFor($size));

        try {
            return (int) $connection->fetchOne('SELECT count(*) FROM book');
        } catch (\Throwable) {
            return null;
        } finally {
            $connection->close();
        }
    }

    private function connect(string $url): Connection
    {
        $parser = new DsnParser(['postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql', 'pgsql' => 'pdo_pgsql']);

        return DriverManager::getConnection($parser->parse($url));
    }
}
