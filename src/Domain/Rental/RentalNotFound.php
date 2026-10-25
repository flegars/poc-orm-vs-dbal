<?php

namespace App\Domain\Rental;

use Symfony\Component\Uid\Uuid;

final class RentalNotFound extends \DomainException
{
    public static function withId(Uuid $id): self
    {
        return new self(\sprintf('Rental %s does not exist.', $id->toRfc4122()));
    }
}
