<?php

namespace App\Infrastructure\Persistence\OrmOptimized;

use App\Application\Catalogue\CatalogueEntry;
use App\Application\Port\Catalogue;
use App\Domain\Author\Author;
use App\Domain\Book\Book;
use App\Domain\Book\BookAuthor;
use App\Domain\Discount\Discount;
use App\Domain\Discount\DiscountPolicy;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;

final class OrmOptimizedCatalogue implements Catalogue
{
    private const int BATCH_SIZE = 1000;

    private DiscountPolicy $policy;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->policy = new DiscountPolicy();
    }

    public function export(\DateTimeImmutable $on): iterable
    {
        $discounts = $this->activeDiscounts($on);
        $books = $this->entityManager
            ->createQuery($this->booksDql())
            ->toIterable([], AbstractQuery::HYDRATE_ARRAY);

        $batch = [];
        foreach ($books as $book) {
            $batch[] = $book;
            if (self::BATCH_SIZE === \count($batch)) {
                yield from $this->entries($batch, $discounts, $on);
                $batch = [];
                $this->entityManager->clear();
            }
        }

        yield from $this->entries($batch, $discounts, $on);
        $this->entityManager->clear();
    }

    public function page(int $page, int $perPage, \DateTimeImmutable $on): array
    {
        if ($page < 1 || $perPage < 1) {
            throw new \InvalidArgumentException('Page and page size must be positive.');
        }

        $discounts = $this->activeDiscounts($on);
        $books = $this->entityManager
            ->createQuery($this->booksDql())
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getArrayResult();

        return iterator_to_array($this->entries($books, $discounts, $on), false);
    }

    private function booksDql(): string
    {
        return 'SELECT b.id, b.title, b.genre, b.priceInCents, b.publishedOn FROM '.Book::class.' b ORDER BY b.title, b.id';
    }

    /**
     * @return list<Discount>
     */
    private function activeDiscounts(\DateTimeImmutable $on): array
    {
        return $this->entityManager
            ->createQuery('SELECT d, da FROM '.Discount::class.' d LEFT JOIN d.authors da WHERE d.startsOn <= :on AND d.endsOn >= :on')
            ->setParameter('on', $on, Types::DATE_IMMUTABLE)
            ->getResult();
    }

    /**
     * @param list<array<string, mixed>> $books
     * @param list<Discount>             $discounts
     *
     * @return \Generator<int, CatalogueEntry>
     */
    private function entries(array $books, array $discounts, \DateTimeImmutable $on): \Generator
    {
        if ([] === $books) {
            return;
        }

        $authors = $this->authorsByBook(array_map(static fn (array $book): int => $book['id'], $books));

        foreach ($books as $book) {
            $authorIds = array_column($authors[$book['id']] ?? [], 'id');
            $best = $this->policy->best($discounts, $book['genre'], $book['publishedOn'], $authorIds, $book['priceInCents'], $on);

            yield new CatalogueEntry(
                $book['id'],
                $book['title'],
                array_column($authors[$book['id']] ?? [], 'name'),
                $book['genre'],
                $book['priceInCents'],
                $best?->id(),
                $best?->priceAfter($book['priceInCents']) ?? $book['priceInCents'],
            );
        }
    }

    /**
     * @param list<int> $bookIds
     *
     * @return array<int, list<array{id: int, name: string}>>
     */
    private function authorsByBook(array $bookIds): array
    {
        $rows = $this->entityManager
            ->createQuery(
                'SELECT IDENTITY(ba.book) AS bookId, a.id, a.name FROM '.BookAuthor::class.' ba
                JOIN '.Author::class.' a WITH a.id = ba.authorId
                WHERE IDENTITY(ba.book) IN (:bookIds)
                ORDER BY a.name, a.id'
            )
            ->setParameter('bookIds', $bookIds, ArrayParameterType::INTEGER)
            ->getArrayResult();

        $authors = [];
        foreach ($rows as $row) {
            $authors[(int) $row['bookId']][] = ['id' => $row['id'], 'name' => $row['name']];
        }

        return $authors;
    }
}
