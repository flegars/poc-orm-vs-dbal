<?php

namespace App\Infrastructure\Persistence\Orm;

use App\Application\Port\PurchaseRepository;
use App\Domain\Sale\Purchase;
use Doctrine\ORM\EntityManagerInterface;

final readonly class OrmPurchaseRepository implements PurchaseRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Purchase $purchase): void
    {
        $this->entityManager->persist($purchase);
        $this->entityManager->flush();
    }
}
