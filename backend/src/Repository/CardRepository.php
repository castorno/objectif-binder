<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\CardSearchQuery;
use App\Entity\Card;
use App\Entity\CardSet;
use App\Entity\Rarity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    /**
     * @return array{items: list<Card>, total: int}
     */
    public function search(CardSearchQuery $query): array
    {
        $paginator = new Paginator($this->createPageQueryBuilder($query)->getQuery());

        return [
            'items' => iterator_to_array($paginator),
            'total' => count($paginator),
        ];
    }

    /**
     * The cards matching a search, unordered and unpaginated. Everything that
     * answers "which cards match?" starts here, so the list, the collection
     * and the completion rate can never disagree on what a filter means.
     *
     * Aliases: c (card), s (set), g (game), r (rarity).
     */
    public function createSearchQueryBuilder(CardSearchQuery $query): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->join('c.cardSet', 's')
            ->join('s.game', 'g')
            ->leftJoin('c.rarity', 'r');

        if (null !== $query->q && '' !== $query->q) {
            $qb->andWhere('LOWER(c.name) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($query->q).'%');
        }

        if (null !== $query->game) {
            $qb->andWhere('g.slug = :game')->setParameter('game', $query->game);
        }

        if (null !== $query->set) {
            $qb->andWhere('s.code = :set')->setParameter('set', $query->set);
        }

        if (null !== $query->rarity) {
            $qb->andWhere('r.name = :rarity')->setParameter('rarity', $query->rarity);
        }

        return $qb;
    }

    /**
     * The requested page of a search, in display order.
     */
    public function createPageQueryBuilder(CardSearchQuery $query): QueryBuilder
    {
        return $this->createSearchQueryBuilder($query)
            ->orderBy('s.code', 'ASC')
            ->addOrderBy('c.numberInSet', 'ASC')
            // Two games may use the same set code: without a last, unique
            // criterion a card could show up on two pages.
            ->addOrderBy('c.id', 'ASC')
            ->setFirstResult(($query->page - 1) * $query->limit)
            ->setMaxResults($query->limit);
    }
}
