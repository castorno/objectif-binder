<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Card;
use App\Entity\CardSet;
use App\Entity\Rarity;
use App\Exception\ForcedRarityNotAllowedException;
use App\Repository\RarityRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gives every card of a sub-set one rarity, named by an administrator. The
 * cards of a sub-set come out of the boosters of its main set at a rate of
 * their own, which a pull rate can only express for a rarity of their own.
 */
final class SetForcedRarityService
{
    public function __construct(
        private readonly RarityRepository $rarityRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param string|null $rarityName null or blank to hand the rarities back to the source:
     *                                the cards get theirs again the next time the set is imported
     *
     * @throws ForcedRarityNotAllowedException when the set is not a sub-set
     */
    public function force(CardSet $cardSet, ?string $rarityName): void
    {
        $rarityName = null === $rarityName ? '' : trim($rarityName);

        if ('' === $rarityName) {
            $cardSet->setForcedRarity(null);
            $this->entityManager->flush();

            return;
        }

        // On a set with boosters of its own, one rarity for all would erase
        // what tells its cards apart.
        if (null === $cardSet->getParent()) {
            throw new ForcedRarityNotAllowedException();
        }

        $rarity = $this->rarityRepository->findOneBy(['game' => $cardSet->getGame(), 'name' => $rarityName]);
        if (null === $rarity) {
            // New rarities go after those the game already has, as the import does.
            $rarity = new Rarity($cardSet->getGame(), $rarityName, $this->rarityRepository->nextSortOrder($cardSet->getGame()));
            $this->entityManager->persist($rarity);
        }

        $cardSet->setForcedRarity($rarity);
        $this->entityManager->flush();

        // One statement, however many cards the set has. It goes straight to
        // the database: a card already loaded keeps its old rarity in memory.
        $this->entityManager->createQueryBuilder()
            ->update(Card::class, 'c')
            ->set('c.rarity', ':rarity')
            ->where('c.cardSet = :cardSet')
            ->setParameter('rarity', $rarity)
            ->setParameter('cardSet', $cardSet)
            ->getQuery()
            ->execute();
    }
}
