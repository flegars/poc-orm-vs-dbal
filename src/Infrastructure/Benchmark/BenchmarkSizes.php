<?php

namespace App\Infrastructure\Benchmark;

final class BenchmarkSizes
{
    public const string DEFAULT = '100,10000,100000';

    /**
     * @return list<int>
     */
    public static function parse(string $value): array
    {
        $sizes = [];
        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            if (!ctype_digit($part) || (int) $part < 1) {
                throw new \InvalidArgumentException(\sprintf('Invalid size "%s": sizes must be positive integers (e.g. --sizes=100,10000).', $part));
            }
            $sizes[] = (int) $part;
        }

        return array_values(array_unique($sizes));
    }
}
