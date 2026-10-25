<?php

namespace App\Tests\Unit\Application\Fake;

use App\Application\Port\Transaction;

final class ImmediateTransaction implements Transaction
{
    public function run(callable $operation): mixed
    {
        return $operation();
    }
}
