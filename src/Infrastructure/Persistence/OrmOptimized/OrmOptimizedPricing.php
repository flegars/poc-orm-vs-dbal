<?php

namespace App\Infrastructure\Persistence\OrmOptimized;

use App\Application\Port\Pricing;
use App\Domain\Book\Book;
use App\Domain\Book\BookNotFound;
use App\Domain\Discount\Discount;
use App\Domain\Discount\DiscountAuthor;
use App\Domain\Discount\DiscountPolicy;
use App\Domain\Discount\PriceBasis;
use App\Domain\Discount\Quote;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

final class OrmOptimizedPricing implements Pricing
{
    private DiscountPolicy $policy;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->policy = new DiscountPolicy();
    }

    public function quote(int $bookId, PriceBasis $basis, \DateTimeImmutable $on): Quote
    {
        $rows = $this->entityManager
            ->createQuery('SELECT b.priceInCents, b.genre, b.publishedOn, ba.authorId FROM '.Book::class.' b LEFT JOIN b.authors ba WHERE b.id = :id')
            ->setParameter('id', $bookId)
            ->getArrayResult();

        if ([] === $rows) {
            throw BookNotFound::withId($bookId);
        }

        $authorIds = array_values(array_filter(array_map(static fn (array $row): ?int => $row['authorId'], $rows), static fn (?int $id): bool => null !== $id));
        $genre = $rows[0]['genre'];
        $publishedOn = $rows[0]['publishedOn'];
        $base = $basis->baseFor($rows[0]['priceInCents']);

        $candidates = $this->entityManager
            ->createQuery(
                'SELECT d, da FROM '.Discount::class.' d LEFT JOIN d.authors da
                WHERE d.startsOn <= :on AND d.endsOn >= :on
                AND (d.genre = :genre
                    OR (d.publishedFrom IS NOT NULL AND :publishedOn BETWEEN d.publishedFrom AND d.publishedTo)
                    OR EXISTS (SELECT 1 FROM '.DiscountAuthor::class.' x WHERE x.discount = d AND x.authorId IN (:authorIds)))'
            )
            ->setParameter('on', $on, Types::DATE_IMMUTABLE)
            ->setParameter('genre', $genre)
            ->setParameter('publishedOn', $publishedOn, Types::DATE_IMMUTABLE)
            ->setParameter('authorIds', $authorIds, ArrayParameterType::INTEGER)
            ->getResult();

        return Quote::fromBest($base, $this->policy->best($candidates, $genre, $publishedOn, $authorIds, $base, $on));
    }
}
