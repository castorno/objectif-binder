<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CardSet;
use App\Entity\PullRate;
use App\Entity\Rarity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PullRate>
 */
class PullRateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PullRate::class);
    }

    public function findOneByCardSetAndRarity(CardSet $cardSet, Rarity $rarity): ?PullRate
    {
        return $this->findOneBy(['cardSet' => $cardSet, 'rarity' => $rarity]);
    }

    /**
     * @return list<PullRate> with their rarity, in one query
     */
    public function findByCardSet(CardSet $cardSet): array
    {
        /** @var list<PullRate> $pullRates */
        $pullRates = $this->createQueryBuilder('p')
            ->addSelect('r')
            ->join('p.rarity', 'r')
            ->where('p.cardSet = :cardSet')
            ->setParameter('cardSet', $cardSet)
            ->getQuery()
            ->getResult();

        return $pullRates;
    }
}
