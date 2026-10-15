<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CardSet;
use App\Exception\BoosterPriceOfSubSetException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The price of a booster of a set, entered by hand: it is what lets the
 * boosters be compared for what they bring per euro.
 */
final class SetBoosterPriceService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param int|null $priceCents in euro cents; null for a price that is not known
     *
     * @throws BoosterPriceOfSubSetException when the cards of the set come in the boosters of another
     */
    public function setPrice(CardSet $cardSet, ?int $priceCents): void
    {
        // A sub-set has no boosters of its own. Taking a price away is always allowed.
        if (null !== $priceCents && null !== $cardSet->getParent()) {
            throw new BoosterPriceOfSubSetException($cardSet->getParent()->getName());
        }

        $cardSet->setBoosterPriceCents($priceCents);
        $this->entityManager->flush();
    }
}
