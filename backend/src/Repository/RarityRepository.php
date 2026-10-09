<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Card;
use App\Entity\Game;
use App\Entity\Rarity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rarity>
 */
class RarityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rarity::class);
    }

    /**
     * The rarities of a game, in their order; with a set code, only those
     * at least one card of that set has.
     *
     * @return list<Rarity>
     */
    public function findByGame(Game $game, ?string $setCode = null): array
    {
        $builder = $this->createQueryBuilder('r')
            ->where('r.game = :game')
            ->setParameter('game', $game)
            ->orderBy('r.sortOrder', 'ASC')
            ->addOrderBy('r.name', 'ASC');

        if (null !== $setCode) {
            $builder
                ->andWhere(sprintf(
                    'EXISTS (SELECT 1 FROM %s c JOIN c.cardSet s WHERE c.rarity = r AND s.game = :game AND s.code = :setCode)',
                    Card::class,
                ))
                ->setParameter('setCode', $setCode);
        }

        /** @var list<Rarity> $rarities */
        $rarities = $builder->getQuery()->getResult();

        return $rarities;
    }
}
