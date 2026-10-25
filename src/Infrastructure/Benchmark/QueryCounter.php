<?php

namespace App\Infrastructure\Benchmark;

final class QueryCounter
{
    private int $count = 0;

    public function reset(): void
    {
        $this->count = 0;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function increment(): void
    {
        ++$this->count;
    }
}
