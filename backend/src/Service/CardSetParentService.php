<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CardSet;
use App\Exception\InvalidParentSetException;
use App\Repository\CardSetRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Says which set the cards of another come in the boosters of. Sets are
 * linked on one level only, which keeps every question about a set and its
 * sub-sets a matter of one comparison.
 */
final class CardSetParentService
{
    public function __construct(
        private readonly CardSetRepository $cardSetRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param CardSet|null $parent null to make the set stand on its own again
     *
     * @throws InvalidParentSetException
     */
    public function setParent(CardSet $cardSet, ?CardSet $parent): void
    {
        if (null !== $parent) {
            if ($parent === $cardSet) {
                throw new InvalidParentSetException('A set cannot be its own parent.');
            }
            if ($parent->getGame() !== $cardSet->getGame()) {
                throw new InvalidParentSetException('A set and its parent belong to the same game.');
            }
            if (null !== $parent->getParent()) {
                throw new InvalidParentSetException(sprintf('"%s" already comes with another set: it cannot be a parent.', $parent->getName()));
            }
            if ([] !== $this->cardSetRepository->findSubSets($cardSet)) {
                throw new InvalidParentSetException(sprintf('"%s" has sub-sets of its own: it cannot become one.', $cardSet->getName()));
            }
        }

        $cardSet->setParent($parent);
        $this->entityManager->flush();
    }
}
