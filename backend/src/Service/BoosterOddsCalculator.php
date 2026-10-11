<?php

declare(strict_types=1);

namespace App\Service;

/**
 * What a booster holds, in probabilities. Pure arithmetic: it is given pull
 * rates and counts of cards, knows nothing of the database, and is where
 * every assumption of the calculation lives.
 *
 * A pull rate says that a booster gives `cards` cards of a rarity every
 * `boosters` boosters: 3 for 1 is three commons in every booster, 2 for 21
 * a classic card in two boosters out of twenty-one. Two assumptions follow:
 *
 * - the cards of a rarity are as likely as one another;
 * - a booster never holds the same card twice.
 *
 * A third one is an approximation: what a booster gives of one rarity says
 * nothing about what it gives of another. In a real booster, two rarities
 * may compete for the same slot. Averages do not suffer from it (the
 * average of a sum is the sum of the averages, dependent or not); the
 * chances of "at least one" do, slightly.
 */
final class BoosterOddsCalculator
{
    /**
     * How many cards of a rarity a booster gives on average, among those
     * wanted: the rate, shared between the cards of the rarity.
     *
     * @param int $total  how many cards have the rarity
     * @param int $wanted how many of them are wanted
     */
    public function expectedCards(int $cards, int $boosters, int $total, int $wanted): float
    {
        if ($total <= 0 || $wanted <= 0) {
            return 0.0;
        }

        // A booster cannot give more cards of a rarity than the rarity has.
        return min($cards / $boosters, $total) * min($wanted, $total) / $total;
    }

    /**
     * The chance that a booster gives at least one of the wanted cards of a
     * rarity.
     *
     * With several cards of the rarity per booster, they are drawn without
     * a card coming twice. A rate that is not a whole number is a mix: 7 for
     * 2 is three cards in half of the boosters and four in the other half.
     *
     * @param int $total  how many cards have the rarity
     * @param int $wanted how many of them are wanted
     */
    public function chanceOfAny(int $cards, int $boosters, int $total, int $wanted): float
    {
        if ($total <= 0 || $wanted <= 0) {
            return 0.0;
        }

        $perBooster = min($cards / $boosters, $total);
        $whole = (int) floor($perBooster);
        $rest = $perBooster - $whole;
        $wanted = min($wanted, $total);

        $noneWhenFewer = $this->chanceOfNone($total, $wanted, $whole);
        $noneWhenMore = $rest > 0.0 ? $this->chanceOfNone($total, $wanted, $whole + 1) : $noneWhenFewer;

        return 1.0 - ((1.0 - $rest) * $noneWhenFewer + $rest * $noneWhenMore);
    }

    /**
     * The chance of at least one event among several that do not depend on
     * one another: what a booster gives of each rarity.
     *
     * @param list<float> $chances
     */
    public function chanceOfAnyAmong(array $chances): float
    {
        $none = 1.0;
        foreach ($chances as $chance) {
            $none *= 1.0 - $chance;
        }

        return 1.0 - $none;
    }

    /**
     * The chance that none of the wanted cards is among `drawn` cards taken
     * from the `total` of a rarity, no card twice.
     */
    private function chanceOfNone(int $total, int $wanted, int $drawn): float
    {
        // More cards drawn than there are unwanted ones: one of them is wanted.
        if ($drawn > $total - $wanted) {
            return 0.0;
        }

        $none = 1.0;
        for ($card = 0; $card < $drawn; ++$card) {
            $none *= ($total - $wanted - $card) / ($total - $card);
        }

        return $none;
    }
}
