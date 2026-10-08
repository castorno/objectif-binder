<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\CardSearchQuery;
use App\Entity\Card;
use App\Entity\OwnedCard;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
    public function __construct(ManagerRegistry $registry)
    {
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
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('c', 's', 'g', 'r')
            ->from(Card::class, 'c')
            ->join('c.cardSet', 's')
            ->join('s.game', 'g')
            ->leftJoin('c.rarity', 'r')
            ->where('EXISTS (SELECT o.id FROM '.OwnedCard::class.' o WHERE o.card = c AND o.user = :user)')
            ->setParameter('user', $user)
            ->orderBy('s.code', 'ASC')
            ->addOrderBy('c.numberInSet', 'ASC');

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

        $qb->setFirstResult(($query->page - 1) * $query->limit)
            ->setMaxResults($query->limit);

        $paginator = new Paginator($qb->getQuery());
        /** @var list<Card> $cards */
        $cards = iterator_to_array($paginator);

        // Then the copies of those cards, all at once: the number of queries
        // does not grow with the size of the page.
        $ownedCardsByCardId = [];
        foreach ($this->findByUserAndCards($user, $cards) as $ownedCard) {
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
     * @param list<Card> $cards
     *
     * @return list<OwnedCard>
     */
    private function findByUserAndCards(User $user, array $cards): array
    {
        if ([] === $cards) {
            return [];
        }

        return $this->findBy(['user' => $user, 'card' => $cards], ['language' => 'ASC']);
    }
}
