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
