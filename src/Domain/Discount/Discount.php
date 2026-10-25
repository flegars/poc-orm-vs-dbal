<?php

namespace App\Domain\Discount;

use App\Domain\Book\Genre;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Discount
{
    private const string DATE_FORMAT = 'Y-m-d';

    private ?Genre $genre = null;
    private ?\DateTimeImmutable $publishedFrom = null;
    private ?\DateTimeImmutable $publishedTo = null;

    /** @var Collection<int, DiscountAuthor> */
    private Collection $authors;

    /**
     * @throws \InvalidArgumentException
     */
    private function __construct(
        private int $id,
        private DiscountKind $kind,
        private int $value,
        private \DateTimeImmutable $startsOn,
        private \DateTimeImmutable $endsOn,
        private DiscountTarget $target,
    ) {
        if (DiscountKind::Percentage === $kind && ($value < 1 || $value > 100)) {
            throw new \InvalidArgumentException(\sprintf('Discount %d: a percentage must be between 1 and 100, got %d.', $id, $value));
        }
        if (DiscountKind::FixedAmount === $kind && $value <= 0) {
            throw new \InvalidArgumentException(\sprintf('Discount %d: a fixed amount must be positive, got %d.', $id, $value));
        }
        if (self::day($startsOn) > self::day($endsOn)) {
            throw new \InvalidArgumentException(\sprintf('Discount %d: startsOn must not be after endsOn.', $id));
        }

        $this->authors = new ArrayCollection();
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function forGenre(
        int $id,
        DiscountKind $kind,
        int $value,
        \DateTimeImmutable $startsOn,
        \DateTimeImmutable $endsOn,
        Genre $genre,
    ): self {
        $discount = new self($id, $kind, $value, $startsOn, $endsOn, DiscountTarget::Genre);
        $discount->genre = $genre;

        return $discount;
    }

    /**
     * @param list<int> $authorIds
     *
     * @throws \InvalidArgumentException
     */
    public static function forAuthors(
        int $id,
        DiscountKind $kind,
        int $value,
        \DateTimeImmutable $startsOn,
        \DateTimeImmutable $endsOn,
        array $authorIds,
    ): self {
        if ([] === $authorIds) {
            throw new \InvalidArgumentException(\sprintf('Discount %d: an author discount needs at least one author.', $id));
        }

        $discount = new self($id, $kind, $value, $startsOn, $endsOn, DiscountTarget::Authors);
        foreach (array_unique($authorIds) as $authorId) {
            $discount->authors->add(new DiscountAuthor($discount, $authorId));
        }

        return $discount;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function forPublicationPeriod(
        int $id,
        DiscountKind $kind,
        int $value,
        \DateTimeImmutable $startsOn,
        \DateTimeImmutable $endsOn,
        \DateTimeImmutable $publishedFrom,
        \DateTimeImmutable $publishedTo,
    ): self {
        if (self::day($publishedFrom) > self::day($publishedTo)) {
            throw new \InvalidArgumentException(\sprintf('Discount %d: publishedFrom must not be after publishedTo.', $id));
        }

        $discount = new self($id, $kind, $value, $startsOn, $endsOn, DiscountTarget::PublicationPeriod);
        $discount->publishedFrom = $publishedFrom;
        $discount->publishedTo = $publishedTo;

        return $discount;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function isActiveOn(\DateTimeImmutable $date): bool
    {
        $day = self::day($date);

        return self::day($this->startsOn) <= $day && $day <= self::day($this->endsOn);
    }

    /**
     * @param list<int> $authorIds
     */
    public function targets(Genre $genre, \DateTimeImmutable $publishedOn, array $authorIds): bool
    {
        return match ($this->target) {
            DiscountTarget::Genre => $this->genre === $genre,
            DiscountTarget::Authors => $this->targetsOneOf($authorIds),
            DiscountTarget::PublicationPeriod => $this->wasPublishedWithinPeriod($publishedOn),
        };
    }

    public function priceAfter(int $basePriceInCents): int
    {
        $reduction = match ($this->kind) {
            DiscountKind::Percentage => (int) round($basePriceInCents * $this->value / 100),
            DiscountKind::FixedAmount => $this->value,
        };

        return max(0, $basePriceInCents - $reduction);
    }

    /**
     * @param list<int> $authorIds
     */
    private function targetsOneOf(array $authorIds): bool
    {
        return $this->authors->exists(
            static fn (int $key, DiscountAuthor $author): bool => \in_array($author->authorId(), $authorIds, true),
        );
    }

    private function wasPublishedWithinPeriod(\DateTimeImmutable $publishedOn): bool
    {
        if (null === $this->publishedFrom || null === $this->publishedTo) {
            return false;
        }

        $day = self::day($publishedOn);

        return self::day($this->publishedFrom) <= $day && $day <= self::day($this->publishedTo);
    }

    private static function day(\DateTimeImmutable $date): string
    {
        return $date->format(self::DATE_FORMAT);
    }
}
