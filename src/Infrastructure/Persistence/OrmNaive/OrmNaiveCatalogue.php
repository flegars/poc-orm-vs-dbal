<?php

namespace App\Infrastructure\Persistence\OrmNaive;

use App\Application\Catalogue\CatalogueEntry;
use App\Application\Port\Catalogue;
use App\Domain\Author\Author;
use App\Domain\Book\Book;
use App\Domain\Discount\Discount;
use App\Domain\Discount\DiscountPolicy;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OrmNaiveCatalogue implements Catalogue
{
    private const array ORDER = ['title' => 'ASC', 'id' => 'ASC'];

    private DiscountPolicy $discountPolicy;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->discountPolicy = new DiscountPolicy();
    }

    public function export(\DateTimeImmutable $on): iterable
    {
        return $this->entries($this->entityManager->getRepository(Book::class)->findBy([], self::ORDER), $on);
    }

    public function page(int $page, int $perPage, \DateTimeImmutable $on): array
    {
        if ($page < 1 || $perPage < 1) {
            throw new \InvalidArgumentException('Page and page size must be positive.');
        }

        $books = $this->entityManager->getRepository(Book::class)->findBy([], self::ORDER, $perPage, ($page - 1) * $perPage);

        return $this->entries($books, $on);
    }

    /**
     * @param list<Book> $books
     *
     * @return list<CatalogueEntry>
     */
    private function entries(array $books, \DateTimeImmutable $on): array
    {
        $discounts = $this->entityManager->getRepository(Discount::class)->findAll();
        $authors = $this->entityManager->getRepository(Author::class);

        $entries = [];
        foreach ($books as $book) {
            $authorIds = $book->authorIds();
            $names = [];
            foreach ($authorIds as $authorId) {
                $author = $authors->find($authorId);
                if (null !== $author) {
                    $names[] = [$author->name(), $author->id()];
                }
            }
            usort($names, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

            $best = $this->discountPolicy->best($discounts, $book->genre(), $book->publishedOn(), $authorIds, $book->priceInCents(), $on);

            $entries[] = new CatalogueEntry(
                $book->id(),
                $book->title(),
                array_column($names, 0),
                $book->genre(),
                $book->priceInCents(),
                $best?->id(),
                $best?->priceAfter($book->priceInCents()) ?? $book->priceInCents(),
            );
        }

        return $entries;
    }
}
