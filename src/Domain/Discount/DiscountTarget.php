<?php

namespace App\Domain\Discount;

enum DiscountTarget: string
{
    case Genre = 'genre';
    case Authors = 'authors';
    case PublicationPeriod = 'publication_period';
}
