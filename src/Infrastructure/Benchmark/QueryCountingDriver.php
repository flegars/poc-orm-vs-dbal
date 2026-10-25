<?php

namespace App\Infrastructure\Benchmark;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

final class QueryCountingDriver extends AbstractDriverMiddleware
{
    public function __construct(Driver $driver, private readonly QueryCounter $counter)
    {
        parent::__construct($driver);
    }

    public function connect(
        #[\SensitiveParameter]
        array $params,
    ): Connection {
        return new QueryCountingConnection(parent::connect($params), $this->counter);
    }
}
