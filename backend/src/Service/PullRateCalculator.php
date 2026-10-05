<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PullRate;
use App\Repository\CardRepository;

final class PullRateCalculator
{
    public function __construct(
        private readonly CardRepository $cardRepository,
    ) {
    }

    /**
     * Odds of pulling one *specific* card of this pull rate's rarity from a
     * booster of its set, as "1 chance in N". Derived from the rarity-level
     * odds and the current card count, never stored: it would go stale as
     * cards of that rarity are added to the set.
     *
     * @throws \DomainException if no card of this rarity exists in the set yet
     */
    public function oddsForSpecificCard(PullRate $pullRate): int
    {
        $cardCount = $this->cardRepository->countByCardSetAndRarity(
            $pullRate->getCardSet(),
            $pullRate->getRarity(),
        );

        if ($cardCount === 0) {
            throw new \DomainException('No card of this rarity exists in this set yet.');
        }

        return $pullRate->getOddsOneIn() * $cardCount;
    }
}
