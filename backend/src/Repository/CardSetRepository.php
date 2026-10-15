<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Card;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Entity\PullRate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CardSet>
 */
class CardSetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardSet::class);
    }

    /**
     * The sets of a game with at least one card that has a picture.
     *
     * @return list<string> their ids
     */
    public function findIdsOfSetsWithPictures(Game $game): array
    {
        /** @var list<string> $ids */
        $ids = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT IDENTITY(c.cardSet)')
            ->from(Card::class, 'c')
            ->join('c.cardSet', 's')
            ->where('s.game = :game')
            ->andWhere('c.imageUrl IS NOT NULL OR c.customImage IS NOT NULL')
            ->setParameter('game', $game)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map(strval(...), $ids);
    }

    /**
     * The sets of a game with boosters of their own whose every rarity, that
     * of the cards of their sub-sets included, has a pull rate.
     *
     * @return list<string> their ids
     */
    public function findIdsOfSetsWithCompletePullRates(Game $game): array
    {
        /** @var list<string> $ids */
        $ids = $this->createQueryBuilder('s')
            ->select('s.id')
            ->where('s.game = :game')
            ->andWhere('s.parent IS NULL')
            // A set without any rarity has no rate to enter, and none entered: not complete.
            ->andWhere(sprintf('EXISTS (SELECT 1 FROM %s p WHERE p.cardSet = s)', PullRate::class))
            // No card of the set or of its sub-sets whose rarity is left without a rate.
            ->andWhere(sprintf(
                'NOT EXISTS (SELECT 1 FROM %s c JOIN c.cardSet cs WHERE (cs = s OR cs.parent = s) AND c.rarity IS NOT NULL'
                .' AND NOT EXISTS (SELECT 1 FROM %s p2 WHERE p2.cardSet = s AND p2.rarity = c.rarity))',
                Card::class,
                PullRate::class,
            ))
            ->setParameter('game', $game)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map(strval(...), $ids);
    }

    /**
     * The sets released in the boosters of this one.
     *
     * @return list<CardSet>
     */
    public function findSubSets(CardSet $cardSet): array
    {
        return $this->findBy(['parent' => $cardSet], ['name' => 'ASC']);
    }

    /**
     * How many sets have boosters of their own (they are no sub-set) and no
     * pull rate yet: those the odds of a booster cannot be worked out for.
     *
     * @param string|null $gameSlug null for every game
     */
    public function countMainSetsWithoutPullRates(?string $gameSlug = null): int
    {
        $builder = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->join('s.game', 'g')
            ->where('s.parent IS NULL')
            ->andWhere(sprintf('NOT EXISTS (SELECT 1 FROM %s p WHERE p.cardSet = s)', PullRate::class));

        if (null !== $gameSlug) {
            $builder->andWhere('g.slug = :game')->setParameter('game', $gameSlug);
        }

        return (int) $builder->getQuery()->getSingleScalarResult();
    }
}
