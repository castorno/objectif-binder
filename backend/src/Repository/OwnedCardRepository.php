<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\CardSearchQuery;
use App\Dto\IdentitySearchQuery;
use App\Entity\Card;
use App\Entity\CardIdentity;
use App\Entity\OwnedCard;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Every query here takes the owner as an argument: there is deliberately no
 * way to read owned cards without saying whose they are.
 *
 * @extends ServiceEntityRepository<OwnedCard>
 */
class OwnedCardRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly CardRepository $cardRepository,
        private readonly CardIdentityRepository $cardIdentityRepository,
    ) {
        parent::__construct($registry, OwnedCard::class);
    }

    public function findOneByUserCardAndLanguage(User $user, Card $card, string $language): ?OwnedCard
    {
        return $this->findOneBy(['user' => $user, 'card' => $card, 'language' => $language]);
    }

    /**
     * The user's copies of one card, one entry per language.
     *
     * @return list<OwnedCard>
     */
    public function findByUserAndCard(User $user, Card $card): array
    {
        return $this->findBy(['user' => $user, 'card' => $card], ['language' => 'ASC']);
    }

    /**
     * One page of the user's collection, filtered like the catalog. A card
     * owned in several languages is one item: pages and totals count cards,
     * not copies.
     *
     * @return array{items: list<array{card: Card, ownedCards: list<OwnedCard>}>, total: int}
     */
    public function searchByUser(User $user, CardSearchQuery $query): array
    {
        // First the page of cards, then their copies. Paginating the
        // owned_card rows directly would cut a card's languages across two pages.
        $qb = $this->cardRepository->createPageQueryBuilder($query);
        $paginator = $this->cardRepository->paginate($this->restrictToOwnership($qb, $user, owned: true));
        /** @var list<Card> $cards */
        $cards = iterator_to_array($paginator);

        $ownedCardsByCardId = $this->findByUserGroupedByCard($user, $cards);

        return [
            'items' => array_map(
                static fn (Card $card): array => ['card' => $card, 'ownedCards' => $ownedCardsByCardId[(string) $card->getId()] ?? []],
                $cards,
            ),
            'total' => count($paginator),
        ];
    }

    /**
     * One page of the cards of a catalog search the user does not own, in any
     * language. The counterpart of searchByUser: together they split a search
     * in two.
     *
     * @return array{items: list<Card>, total: int}
     */
    public function searchMissingByUser(User $user, CardSearchQuery $query): array
    {
        $qb = $this->cardRepository->createPageQueryBuilder($query);
        $paginator = $this->cardRepository->paginate($this->restrictToOwnership($qb, $user, owned: false));

        return [
            'items' => iterator_to_array($paginator, preserve_keys: false),
            'total' => count($paginator),
        ];
    }

    /**
     * How much of a catalog search the user owns. A card owned in several
     * languages counts once.
     *
     * @return array{total: int, owned: int, ownedOnPage: array<string, list<OwnedCard>>} total and owned cover the whole
     *                                                                                    search; ownedOnPage, by card id,
     *                                                                                    only the requested page of it
     */
    public function completionByUser(User $user, CardSearchQuery $query): array
    {
        $total = $this->cardRepository->countSearch($query);

        if (0 === $total) {
            return ['total' => 0, 'owned' => 0, 'ownedOnPage' => []];
        }

        $owned = $this->countOwnedByUser($user, $query);

        if (0 === $owned) {
            return ['total' => $total, 'owned' => 0, 'ownedOnPage' => []];
        }

        // The same page the catalog shows for this search, then what is owned
        // of those cards: filtering first would shift the page.
        $page = $this->cardRepository->createPageQueryBuilder($query)->getQuery()->getResult();

        return ['total' => $total, 'owned' => $owned, 'ownedOnPage' => $this->findByUserGroupedByCard($user, $page)];
    }

    /**
     * How many cards of a catalog search the user owns, in any language.
     */
    public function countOwnedByUser(User $user, CardSearchQuery $query): int
    {
        return (int) $this->restrictToOwnership($this->cardRepository->createSearchQueryBuilder($query), $user, owned: true)
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * How many identities of a search the user has started: those they own at
     * least one card of, in any language. What completing a game's index of
     * creatures means.
     */
    public function countIdentitiesStartedByUser(User $user, IdentitySearchQuery $query): int
    {
        return (int) $this->cardIdentityRepository->createSearchQueryBuilder($query)
            ->select('COUNT(i.id)')
            // The identities of the cards the user owns are listed once, and
            // the search is matched against that list. Asking instead, for
            // each identity, whether an owned card shows it made the database
            // go through the collection once per identity: 1.3 s for 8,000
            // owned cards and 1,000 identities, against a few milliseconds.
            ->andWhere('i.id IN (SELECT ownedIdentity.id FROM '.OwnedCard::class.' o JOIN o.card ownedCard JOIN ownedCard.identities ownedIdentity WHERE o.user = :user)')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * How many cards of each of the given identities the user owns, in one
     * query. A card owned in several languages counts once; a card with
     * several identities counts for each of them.
     *
     * @param list<CardIdentity> $identities
     *
     * @return array<string, int> by identity id; no entry for an identity the user owns nothing of
     */
    public function countOwnedByUserAndIdentity(User $user, array $identities): array
    {
        if ([] === $identities) {
            return [];
        }

        $rows = $this->createQueryBuilder('o')
            ->select('i.id AS id', 'COUNT(DISTINCT c.id) AS cards')
            ->join('o.card', 'c')
            ->join('c.identities', 'i')
            ->where('o.user = :user')
            ->andWhere('i IN (:identities)')
            ->setParameter('user', $user)
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
     * The user's copies of the given cards, by card id, in one query whatever
     * the number of cards. Cards the user does not own have no entry.
     *
     * @param list<Card> $cards
     *
     * @return array<string, list<OwnedCard>>
     */
    private function findByUserGroupedByCard(User $user, array $cards): array
    {
        if ([] === $cards) {
            return [];
        }

        $ownedCardsByCardId = [];
        foreach ($this->findBy(['user' => $user, 'card' => $cards], ['language' => 'ASC']) as $ownedCard) {
            $ownedCardsByCardId[(string) $ownedCard->getCard()->getId()][] = $ownedCard;
        }

        return $ownedCardsByCardId;
    }

    /**
     * Narrows a query on cards (alias c) to those the user owns, or to those
     * the user does not own.
     */
    private function restrictToOwnership(QueryBuilder $qb, User $user, bool $owned): QueryBuilder
    {
        return $qb
            ->andWhere(($owned ? '' : 'NOT ').'EXISTS (SELECT o.id FROM '.OwnedCard::class.' o WHERE o.card = c AND o.user = :user)')
            ->setParameter('user', $user);
    }
}
