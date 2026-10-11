<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\BoosterOddsCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The arithmetic of what a booster holds. Every expected figure can be
 * worked out by hand from the comment next to it.
 */
final class BoosterOddsCalculatorTest extends TestCase
{
    private BoosterOddsCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new BoosterOddsCalculator();
    }

    #[DataProvider('chances')]
    public function testChanceOfAtLeastOneWantedCardOfARarity(int $cards, int $boosters, int $total, int $wanted, float $expected): void
    {
        self::assertEqualsWithDelta($expected, $this->calculator->chanceOfAny($cards, $boosters, $total, $wanted), 0.00001);
    }

    /**
     * @return iterable<string, array{int, int, int, int, float}>
     */
    public static function chances(): iterable
    {
        // One card every eight boosters, one wanted card among twelve: 1/8 × 1/12.
        yield 'a rarity that does not come in every booster' => [1, 8, 12, 1, 1 / 96];
        // The rate, times the share of the rarity that is wanted: 2/21 × 26/30.
        yield 'several wanted cards of such a rarity' => [2, 21, 30, 26, 2 / 21 * 26 / 30];
        // One card per booster, taken among 30 that are all wanted.
        yield 'a card in every booster, all wanted' => [1, 1, 30, 30, 1.0];
        // Three different cards among 68, three of them wanted: 1 − (65×64×63)/(68×67×66).
        yield 'several cards per booster, no card twice' => [3, 1, 68, 3, 1 - (65 * 64 * 63) / (68 * 67 * 66)];
        // Three cards among four with two wanted: only two are unwanted, one of the three is wanted.
        yield 'more cards drawn than there are unwanted ones' => [3, 1, 4, 2, 1.0];
        // 7 for 2: three cards in half of the boosters, four in the other half, one wanted among ten.
        yield 'a rate that is not a whole number' => [7, 2, 10, 1, 0.5 * 3 / 10 + 0.5 * 4 / 10];
        // Five cards per booster of a rarity that has three: all three come, every time.
        yield 'more cards per booster than the rarity has' => [5, 1, 3, 1, 1.0];
        yield 'nothing wanted' => [3, 1, 68, 0, 0.0];
        yield 'a rarity without card' => [3, 1, 0, 0, 0.0];
    }

    #[DataProvider('averages')]
    public function testAverageNumberOfWantedCardsOfARarity(int $cards, int $boosters, int $total, int $wanted, float $expected): void
    {
        self::assertEqualsWithDelta($expected, $this->calculator->expectedCards($cards, $boosters, $total, $wanted), 0.00001);
    }

    /**
     * @return iterable<string, array{int, int, int, int, float}>
     */
    public static function averages(): iterable
    {
        // Three commons per booster, 62 of the 68 commons wanted.
        yield 'several cards per booster' => [3, 1, 68, 62, 3 * 62 / 68];
        yield 'a rarity that does not come in every booster' => [7, 16, 18, 14, 7 / 16 * 14 / 18];
        yield 'all of them wanted: the rate itself' => [1, 1, 30, 30, 1.0];
        // A booster cannot give five cards of a rarity that has three.
        yield 'more cards per booster than the rarity has' => [5, 1, 3, 2, 2.0];
        yield 'nothing wanted' => [3, 1, 68, 0, 0.0];
    }

    /**
     * A species printed in several rarities can come from any of them.
     */
    public function testChanceOfAtLeastOneAmongSeveralRarities(): void
    {
        // Three commons and three illustration rares of the same species:
        // 12.84 % from the commons, 2.98 % from the illustration rares, 15.4 % from either.
        $common = $this->calculator->chanceOfAny(3, 1, 68, 3);
        $illustration = $this->calculator->chanceOfAny(5, 28, 18, 3);

        self::assertEqualsWithDelta(1 - (1 - 0.128422) * (1 - 0.029762), $this->calculator->chanceOfAnyAmong([$common, $illustration]), 0.00001);
        self::assertSame(0.0, $this->calculator->chanceOfAnyAmong([]));
        self::assertSame(1.0, $this->calculator->chanceOfAnyAmong([0.2, 1.0]));
    }
}
