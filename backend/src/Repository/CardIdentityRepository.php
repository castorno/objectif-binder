<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\IdentitySearchQuery;
use App\Entity\Card;
use App\Entity\CardIdentity;
use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
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
        /** @var Paginator<CardIdentity> $paginator */
        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);

        return [
            'items' => iterator_to_array($paginator, preserve_keys: false),
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
                ->setParameter('q', LikePattern::containing($query->q));
        }

        if (null !== $query->game) {
            $qb->andWhere('g.slug = :game')->setParameter('game', $query->game);
        }

        if (null !== $query->group && '' !== $query->group) {
            $qb->andWhere('i.groupName = :group')->setParameter('group', $query->group);
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

    /**
     * The picture standing for each of the given identities: that of its
     * first card, the earliest released among those that have a picture. In
     * one query, and computed rather than stored, like the card counts.
     *
     * Written in SQL: "the first row of each group" is PostgreSQL's
     * DISTINCT ON, which the ORM's query language cannot express.
     *
     * @param list<CardIdentity> $identities
     *
     * @return array<string, string> by identity id; no entry for an identity none of whose cards has a picture
     */
    public function findImageUrlsByIdentity(array $identities): array
    {
        if ([] === $identities) {
            return [];
        }

        $rows = $this->getEntityManager()->getConnection()->executeQuery(
            <<<'SQL'
                SELECT DISTINCT ON (l.card_identity_id) l.card_identity_id AS id, c.image_url
                FROM card_identity_link l
                JOIN card c ON c.id = l.card_id
                JOIN card_set s ON s.id = c.card_set_id
                WHERE l.card_identity_id IN (:identities) AND c.image_url IS NOT NULL
                -- A set without release date comes last; the rest of the
                -- order only makes the choice the same from one call to the next.
                ORDER BY l.card_identity_id, s.release_date ASC NULLS LAST, s.code, c.number_in_set, c.id
                SQL,
            ['identities' => array_map(static fn (CardIdentity $identity): string => $identity->getId()->toRfc4122(), $identities)],
            ['identities' => ArrayParameterType::STRING],
        )->fetchAllKeyValue();

        /** @var array<string, string> $rows */
        return $rows;
    }

    /**
     * The groups the identities of a game are sorted into, in the game's
     * order, with how many identities each holds. Read from the identities
     * themselves: a group exists as long as an identity names it.
     *
     * @return list<array{name: string, identityCount: int}>
     */
    public function findGroupsByGame(Game $game): array
    {
        $rows = $this->createQueryBuilder('i')
            ->select('i.groupName AS name', 'COUNT(i.id) AS identityCount', 'MIN(i.groupOrder) AS HIDDEN position')
            ->where('i.game = :game')
            ->andWhere('i.groupName IS NOT NULL')
            ->setParameter('game', $game)
            ->groupBy('i.groupName')
            ->orderBy('position', 'ASC')
            ->addOrderBy('i.groupName', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_map(
            static fn (array $row): array => ['name' => (string) $row['name'], 'identityCount' => (int) $row['identityCount']],
            $rows,
        ));
    }
}
