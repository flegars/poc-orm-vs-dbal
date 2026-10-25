<?php

namespace App\Infrastructure\Persistence;

use App\Application\Port\Transaction;
use Doctrine\DBAL\Connection;

final readonly class DbalTransaction implements Transaction
{
    public function __construct(private Connection $connection)
    {
    }

    public function run(callable $operation): mixed
    {
        return $this->connection->transactional($operation);
    }
}
