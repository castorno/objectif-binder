<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\IdentitySearchQuery;
use App\Entity\Card;
use App\Entity\CardIdentity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CardIdentity>
 */
class CardIdentityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CardIdentity::class);
    }

    /**
     * One page of identities, in each game's own order.
     *
     * @return array{items: list<CardIdentity>, total: int}
     */
    public function search(IdentitySearchQuery $query): array
    {
        $qb = $this->createSearchQueryBuilder($query)
            ->addSelect('g')
            ->orderBy('g.name', 'ASC')
            // Numbered identities first, in their numbering; PostgreSQL sorts
            // the ones without a number after them, where the name decides.
            ->addOrderBy('i.sortOrder', 'ASC')
            ->addOrderBy('i.name', 'ASC')
            ->addOrderBy('i.id', 'ASC')
            ->setFirstResult(($query->page - 1) * $query->limit)
            ->setMaxResults($query->limit);

        // No join multiplies rows here: see CardRepository::paginate().
        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);

        return [
            'items' => iterator_to_array($paginator),
            'total' => count($paginator),
        ];
    }

    /**
     * The identities matching a search, unordered and unpaginated: what the
     * list and every count about it start from.
     *
     * Aliases: i (identity), g (game).
     */
    public function createSearchQueryBuilder(IdentitySearchQuery $query): QueryBuilder
    {
        $qb = $this->createQueryBuilder('i')->join('i.game', 'g');

        if (null !== $query->q && '' !== $query->q) {
            $qb->andWhere('LOWER(i.name) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower($query->q).'%');
        }

        if (null !== $query->game) {
            $qb->andWhere('g.slug = :game')->setParameter('game', $query->game);
        }

        return $qb;
    }

    public function countSearch(IdentitySearchQuery $query): int
    {
        return (int) $this->createSearchQueryBuilder($query)
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * How many cards each of the given identities groups, in one query. A card
     * with several identities counts for each of them. Computed, never stored:
     * it would go stale with every import.
     *
     * @param list<CardIdentity> $identities
     *
     * @return array<string, int> by identity id; no entry for an identity without card
     */
    public function countCardsByIdentity(array $identities): array
    {
        if ([] === $identities) {
            return [];
        }

        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('i.id AS id', 'COUNT(c.id) AS cards')
            ->from(Card::class, 'c')
            ->join('c.identities', 'i')
            ->where('i IN (:identities)')
            ->setParameter('identities', $identities)
            ->groupBy('i.id')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['id']] = (int) $row['cards'];
        }

        return $counts;
    }
}
