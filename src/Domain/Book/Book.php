<?php

namespace App\Domain\Book;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Book
{
    /** @var Collection<int, BookAuthor> */
    private Collection $authors;

    /**
     * @param list<int> $authorIds
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        private int $id,
        private string $title,
        private int $priceInCents,
        private Genre $genre,
        private \DateTimeImmutable $publishedOn,
        private int $stock,
        array $authorIds,
    ) {
        if ([] === $authorIds) {
            throw new \InvalidArgumentException(\sprintf('Book %d needs at least one author.', $id));
        }
        if ($priceInCents < 0) {
            throw new \InvalidArgumentException(\sprintf('Book %d cannot have a negative price.', $id));
        }
        if ($stock < 0) {
            throw new \InvalidArgumentException(\sprintf('Book %d cannot have a negative stock.', $id));
        }

        $this->authors = new ArrayCollection();
        foreach (array_unique($authorIds) as $authorId) {
            $this->authors->add(new BookAuthor($this, $authorId));
        }
    }

    public function id(): int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function priceInCents(): int
    {
        return $this->priceInCents;
    }

    public function genre(): Genre
    {
        return $this->genre;
    }

    public function publishedOn(): \DateTimeImmutable
    {
        return $this->publishedOn;
    }

    public function stock(): int
    {
        return $this->stock;
    }

    /**
     * @return list<int>
     */
    public function authorIds(): array
    {
        $authorIds = array_values($this->authors->map(static fn (BookAuthor $author): int => $author->authorId())->toArray());
        sort($authorIds);

        return $authorIds;
    }

    /**
     * @throws OutOfStock
     */
    public function takeCopy(): void
    {
        if ($this->stock <= 0) {
            throw OutOfStock::forBook($this->id);
        }

        --$this->stock;
    }

    public function putCopyBack(): void
    {
        ++$this->stock;
    }
}
