<?php

namespace App\Domain\Rental;

use App\Domain\Discount\Quote;
use Symfony\Component\Uid\Uuid;

class Rental
{
    public const int DURATION_DAYS = 14;

    public function __construct(
        private Uuid $id,
        private int $bookId,
        private \DateTimeImmutable $rentedOn,
        private \DateTimeImmutable $dueOn,
        private int $basePriceInCents,
        private ?int $discountId,
        private int $priceInCents,
        private ?\DateTimeImmutable $returnedOn = null,
    ) {
    }

    public static function start(Uuid $id, int $bookId, Quote $quote, \DateTimeImmutable $on): self
    {
        return new self(
            $id,
            $bookId,
            $on,
            $on->modify(\sprintf('+%d days', self::DURATION_DAYS)),
            $quote->basePriceInCents,
            $quote->discountId,
            $quote->finalPriceInCents,
        );
    }

    /**
     * @throws RentalAlreadyReturned
     */
    public function markReturned(\DateTimeImmutable $on): void
    {
        if ($this->isReturned()) {
            throw RentalAlreadyReturned::withId($this->id);
        }

        $this->returnedOn = $on;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function bookId(): int
    {
        return $this->bookId;
    }

    public function rentedOn(): \DateTimeImmutable
    {
        return $this->rentedOn;
    }

    public function dueOn(): \DateTimeImmutable
    {
        return $this->dueOn;
    }

    public function basePriceInCents(): int
    {
        return $this->basePriceInCents;
    }

    public function discountId(): ?int
    {
        return $this->discountId;
    }

    public function priceInCents(): int
    {
        return $this->priceInCents;
    }

    public function returnedOn(): ?\DateTimeImmutable
    {
        return $this->returnedOn;
    }

    public function isReturned(): bool
    {
        return null !== $this->returnedOn;
    }
}
