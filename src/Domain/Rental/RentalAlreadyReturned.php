<?php

namespace App\Domain\Rental;

use Symfony\Component\Uid\Uuid;

final class RentalAlreadyReturned extends \DomainException
{
    public static function withId(Uuid $id): self
    {
        return new self(\sprintf('Rental %s has already been returned.', $id->toRfc4122()));
    }
}
