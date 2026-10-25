<?php

namespace App\Infrastructure\Benchmark;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

final class QueryCountingStatement extends AbstractStatementMiddleware
{
    public function __construct(Statement $statement, private readonly QueryCounter $counter)
    {
        parent::__construct($statement);
    }

    public function execute(): Result
    {
        $this->counter->increment();

        return parent::execute();
    }
}
