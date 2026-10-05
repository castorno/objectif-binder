<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Card;
use App\Entity\CardSet;
use App\Entity\Rarity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Card>
 */
class CardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Card::class);
    }

    public function countByCardSetAndRarity(CardSet $cardSet, Rarity $rarity): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.cardSet = :cardSet')
            ->andWhere('c.rarity = :rarity')
            ->setParameter('cardSet', $cardSet)
            ->setParameter('rarity', $rarity)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
