<?php

namespace App\Infrastructure\Benchmark;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

#[AsMiddleware]
final readonly class QueryCountingMiddleware implements Middleware
{
    public function __construct(private QueryCounter $counter)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        return new QueryCountingDriver($driver, $this->counter);
    }
}
