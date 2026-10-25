<?php

namespace App\Infrastructure\Persistence\OrmNaive;

use App\Application\Port\Pricing;
use App\Domain\Book\Book;
use App\Domain\Book\BookNotFound;
use App\Domain\Discount\Discount;
use App\Domain\Discount\DiscountPolicy;
use App\Domain\Discount\PriceBasis;
use App\Domain\Discount\Quote;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OrmNaivePricing implements Pricing
{
    private DiscountPolicy $discountPolicy;

    public function __construct(private EntityManagerInterface $entityManager)
    {
        $this->discountPolicy = new DiscountPolicy();
    }

    public function quote(int $bookId, PriceBasis $basis, \DateTimeImmutable $on): Quote
    {
        $book = $this->entityManager->find(Book::class, $bookId) ?? throw BookNotFound::withId($bookId);
        $base = $basis->baseFor($book->priceInCents());

        $best = $this->discountPolicy->best(
            $this->entityManager->getRepository(Discount::class)->findAll(),
            $book->genre(),
            $book->publishedOn(),
            $book->authorIds(),
            $base,
            $on,
        );

        return Quote::fromBest($base, $best);
    }
}
