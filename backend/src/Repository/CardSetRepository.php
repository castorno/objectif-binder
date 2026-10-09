<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Card;
use App\Entity\CardSet;
use App\Entity\Game;
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
            ->andWhere('c.imageUrl IS NOT NULL')
            ->setParameter('game', $game)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map(strval(...), $ids);
    }
}
