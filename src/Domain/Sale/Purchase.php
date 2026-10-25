<?php

namespace App\Domain\Sale;

use App\Domain\Discount\Quote;
use Symfony\Component\Uid\Uuid;

class Purchase
{
    public function __construct(
        private Uuid $id,
        private int $bookId,
        private \DateTimeImmutable $purchasedOn,
        private int $basePriceInCents,
        private ?int $discountId,
        private int $paidInCents,
    ) {
    }

    public static function record(Uuid $id, int $bookId, Quote $quote, \DateTimeImmutable $on): self
    {
        return new self($id, $bookId, $on, $quote->basePriceInCents, $quote->discountId, $quote->finalPriceInCents);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function bookId(): int
    {
        return $this->bookId;
    }

    public function purchasedOn(): \DateTimeImmutable
    {
        return $this->purchasedOn;
    }

    public function basePriceInCents(): int
    {
        return $this->basePriceInCents;
    }

    public function discountId(): ?int
    {
        return $this->discountId;
    }

    public function paidInCents(): int
    {
        return $this->paidInCents;
    }
}
