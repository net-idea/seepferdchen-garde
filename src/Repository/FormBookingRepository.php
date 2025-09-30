<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\FormBookingEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FormBookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormBookingEntity::class);
    }

    public function findOneByToken(string $token): ?FormBookingEntity
    {
        return $this->findOneBy(['confirmationToken' => $token]);
    }
}
