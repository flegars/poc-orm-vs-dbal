<?php

namespace App\Domain\Discount;

enum DiscountKind: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
}
