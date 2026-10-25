<?php

namespace App\Application;

enum Variant: string
{
    case OrmNaive = 'orm-naive';
    case OrmOptimized = 'orm-optimized';
    case Dbal = 'dbal';
}
