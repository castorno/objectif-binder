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

    /**
     * Every pull rate entered for a set with boosters of its own, with its
     * set, game and rarity, in one query. Rates left on a set that has since
     * become a sub-set are not used, and are left out.
     *
     * @param string|null $gameSlug null for every game
     *
     * @return list<PullRate>
     */
    public function findOfMainSets(?string $gameSlug = null): array
    {
        $builder = $this->createQueryBuilder('p')
            ->addSelect('s', 'g', 'r')
            ->join('p.cardSet', 's')
            ->join('s.game', 'g')
            ->join('p.rarity', 'r')
            ->where('s.parent IS NULL');

        if (null !== $gameSlug) {
            $builder->andWhere('g.slug = :game')->setParameter('game', $gameSlug);
        }

        /** @var list<PullRate> $pullRates */
        $pullRates = $builder->getQuery()->getResult();

        return $pullRates;
    }
}
