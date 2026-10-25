<?php

namespace App\Infrastructure\Benchmark;

enum CellStatus: string
{
    case Ok = 'ok';
    case Timeout = 'timeout';
    case Failed = 'failed';
}
