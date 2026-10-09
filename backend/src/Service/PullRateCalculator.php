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
     * booster of its set, as "1 chance in N". Derived from the rate of the
     * rarity and the current card count, never stored: it would go stale as
     * cards of that rarity are added to the set.
     *
     * A booster giving 4 commons of a set that has 66 gives one given common
     * 4 times in 66 boosters: 1 chance in 16.5. This holds as long as a
     * booster never has the same card twice, which is how they are packed.
     *
     * @return int|float a whole number whenever the odds are one; never under 1,
     *                   which stands for a card found in every booster
     *
     * @throws \DomainException if no card of this rarity exists in the set yet
     */
    public function oddsForSpecificCard(PullRate $pullRate): int|float
    {
        $cardsOfRarity = $this->cardRepository->countByCardSetAndRarity(
            $pullRate->getCardSet(),
            $pullRate->getRarity(),
        );

        if (0 === $cardsOfRarity) {
            throw new \DomainException('No card of this rarity exists in this set yet.');
        }

        $boosters = $pullRate->getBoosterCount() * $cardsOfRarity;
        $cards = $pullRate->getCardCount();

        if ($boosters <= $cards) {
            return 1;
        }

        return 0 === $boosters % $cards ? intdiv($boosters, $cards) : round($boosters / $cards, 2);
    }
}
