<?php

namespace App\Infrastructure\Persistence\Orm;

use App\Application\Port\RentalRepository;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalNotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class OrmRentalRepository implements RentalRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function get(Uuid $id): Rental
    {
        return $this->entityManager->find(Rental::class, $id) ?? throw RentalNotFound::withId($id);
    }

    public function save(Rental $rental): void
    {
        $this->entityManager->persist($rental);
        $this->entityManager->flush();
    }
}
