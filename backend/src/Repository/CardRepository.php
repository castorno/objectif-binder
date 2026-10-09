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
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Card>
 */
class CardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Card::class);
    }

    /**
     * How many cards of a set, its sub-sets included, have a rarity.
     */
    public function countByCardSetAndRarity(CardSet $cardSet, Rarity $rarity): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->join('c.cardSet', 's')
            ->where('s = :cardSet OR s.parent = :cardSet')
            ->andWhere('c.rarity = :rarity')
            ->setParameter('cardSet', $cardSet)
            ->setParameter('rarity', $rarity)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * How many cards of a set, its sub-sets included, have each rarity.
     * Cards without rarity are left out.
     *
     * @return array<string, int> by rarity id
     */
    public function countByRarityInCardSet(CardSet $cardSet): array
    {
        /** @var list<array{rarityId: mixed, cardCount: int|string}> $rows */
        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.rarity) AS rarityId', 'COUNT(c.id) AS cardCount')
            ->join('c.cardSet', 's')
            ->where('s = :cardSet OR s.parent = :cardSet')
            ->andWhere('c.rarity IS NOT NULL')
            ->groupBy('c.rarity')
            ->setParameter('cardSet', $cardSet)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['rarityId']] = (int) $row['cardCount'];
        }

        return $counts;
    }

    /**
     * @return array{items: list<Card>, total: int}
     */
    public function search(CardSearchQuery $query): array
    {
        $paginator = $this->paginate($this->createPageQueryBuilder($query));

        return [
            'items' => iterator_to_array($paginator, preserve_keys: false),
            'total' => count($paginator),
        ];
    }

    public function countSearch(CardSearchQuery $query): int
    {
        return (int) $this->createSearchQueryBuilder($query)
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Runs a query made by createPageQueryBuilder, plus the count of the whole
     * search it is a page of.
     *
     * @return Paginator<Card>
     */
    public function paginate(QueryBuilder $pageQueryBuilder): Paginator
    {
        // A card has one set, one game, one rarity: no join here multiplies
        // rows, so the paginator can limit the query as it is instead of
        // first looking up the ids of the page with a query of its own.
        return new Paginator($pageQueryBuilder->getQuery(), fetchJoinCollection: false);
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
                ->setParameter('q', LikePattern::containing($query->q));
        }

        if (null !== $query->game) {
            $qb->andWhere('g.slug = :game')->setParameter('game', $query->game);
        }

        if (null !== $query->set) {
            // A set comes with the sets released in its boosters (see CardSet::$parent).
            $qb->leftJoin('s.parent', 'ps')
                ->andWhere('s.code = :set OR ps.code = :set')
                ->setParameter('set', $query->set);
        }

        if (null !== $query->rarity) {
            $qb->andWhere('r.name = :rarity')->setParameter('rarity', $query->rarity);
        }

        // Neither condition joins the identities: a card with two of them
        // would come out twice, and break the page sizes and the counts.
        if (CardSearchQuery::WITHOUT_IDENTITY === $query->identity) {
            $qb->andWhere('c.identities IS EMPTY');
        } elseif (null !== $query->identity) {
            $qb->andWhere(':identity MEMBER OF c.identities')
                ->setParameter('identity', Uuid::fromString($query->identity), UuidType::NAME);
        }

        return $qb;
    }

    /**
     * The requested page of a search, in display order: by release date of
     * the set, then by number in the set. Each card comes with
     * its set, game and rarity, read in the same query: a list shows all of
     * them, and fetching them card by card would cost three more queries per
     * card (the "N+1" problem).
     */
    public function createPageQueryBuilder(CardSearchQuery $query): QueryBuilder
    {
        return $this->createSearchQueryBuilder($query)
            ->addSelect('s', 'g', 'r')
            // Sets in the order they came out, like the pages of a binder.
            // PostgreSQL sorts missing values last in ascending order: a
            // set without release date goes after all the others.
            ->orderBy('s.releaseDate', 'ASC')
            // Two sets may share a date; the code keeps each one together.
            ->addOrderBy('s.code', 'ASC')
            ->addOrderBy('c.numberInSet', 'ASC')
            // Two games may use the same set code: without a last, unique
            // criterion a card could show up on two pages.
            ->addOrderBy('c.id', 'ASC')
            ->setFirstResult(($query->page - 1) * $query->limit)
            ->setMaxResults($query->limit);
    }
}
