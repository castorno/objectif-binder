<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\CardSearchQuery;
use App\Entity\Card;
use App\Entity\OwnedCard;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
        // First the page of cards. Paginating the owned_card rows directly
        // would cut a card's languages across two pages.
        $qb = $this->cardRepository->createPageQueryBuilder($query)->addSelect('s', 'g', 'r');
        $paginator = new Paginator($this->restrictToOwnership($qb, $user, owned: true)->getQuery());
        /** @var list<Card> $cards */
        $cards = iterator_to_array($paginator);

        // Then the copies of those cards, all at once: the number of queries
        // does not grow with the size of the page.
        $ownedCards = [] === $cards ? [] : $this->findBy(['user' => $user, 'card' => $cards], ['language' => 'ASC']);
        $ownedCardsByCardId = [];
        foreach ($ownedCards as $ownedCard) {
            $ownedCardsByCardId[(string) $ownedCard->getCard()->getId()][] = $ownedCard;
        }

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
        $qb = $this->cardRepository->createPageQueryBuilder($query)->addSelect('s', 'g', 'r');
        $paginator = new Paginator($this->restrictToOwnership($qb, $user, owned: false)->getQuery());

        return [
            'items' => iterator_to_array($paginator),
            'total' => count($paginator),
        ];
    }

    /**
     * How much of a catalog search the user owns. A card owned in several
     * languages counts once.
     *
     * @return array{total: int, owned: int, ownedCardIds: list<string>} total and owned cover the whole search;
     *                                                                   ownedCardIds only the requested page of it
     */
    public function completionByUser(User $user, CardSearchQuery $query): array
    {
        $total = (int) $this->cardRepository->createSearchQueryBuilder($query)
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        if (0 === $total) {
            return ['total' => 0, 'owned' => 0, 'ownedCardIds' => []];
        }

        $owned = (int) $this->restrictToOwnership($this->cardRepository->createSearchQueryBuilder($query), $user, owned: true)
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // The same page the catalog shows for this search, then the owned
        // cards among those: filtering first would shift the page.
        $page = $this->cardRepository->createPageQueryBuilder($query)->getQuery()->getResult();
        $ownedOnPage = [] === $page || 0 === $owned ? [] : $this->createQueryBuilder('o')
            ->select('DISTINCT IDENTITY(o.card)')
            ->where('o.user = :user')
            ->andWhere('o.card IN (:cards)')
            ->setParameter('user', $user)
            ->setParameter('cards', $page)
            ->getQuery()
            ->getSingleColumnResult();
        $ownedOnPage = array_map(strval(...), $ownedOnPage);

        return [
            'total' => $total,
            'owned' => $owned,
            // In the order of the page.
            'ownedCardIds' => array_values(array_filter(
                array_map(static fn (Card $card): string => (string) $card->getId(), $page),
                static fn (string $id): bool => \in_array($id, $ownedOnPage, true),
            )),
        ];
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
