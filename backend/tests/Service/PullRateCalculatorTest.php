<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Card;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Entity\PullRate;
use App\Entity\Rarity;
use App\Repository\CardRepository;
use App\Service\PullRateCalculator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PullRateCalculatorTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PullRateCalculator $calculator;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->calculator = new PullRateCalculator($container->get(CardRepository::class));

        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        $this->em->close();

        parent::tearDown();
    }

    /**
     * Several cards of a rarity in one booster, or one every few boosters:
     * the odds of one card are the rate shared between the cards of the rarity.
     */
    #[DataProvider('ratesAndOdds')]
    public function testOddsFollowHowManyCardsOfTheRarityABoosterGives(int $cards, int $boosters, int $cardsOfRarity, int|float $expected): void
    {
        $game = new Game('Game '.uniqid(), 'game-'.uniqid());
        $set = new CardSet($game, 'Set', 'SET-'.uniqid());
        $common = new Rarity($game, 'Common', 1);
        $this->em->persist($game);
        $this->em->persist($set);
        $this->em->persist($common);
        for ($number = 1; $number <= $cardsOfRarity; ++$number) {
            $this->em->persist(new Card($set, 'Card '.$number, (string) $number)->setRarity($common));
        }
        $pullRate = new PullRate($set, $common, $cards, $boosters);
        $this->em->persist($pullRate);
        $this->em->flush();

        self::assertSame($expected, $this->calculator->oddsForSpecificCard($pullRate));
    }

    /**
     * @return iterable<string, array{int, int, int, int|float}>
     */
    public static function ratesAndOdds(): iterable
    {
        // The plain case: the odds of the rarity, times the cards that share it.
        yield 'one every 51 boosters among 3' => [1, 51, 3, 153];
        yield 'four per booster among 66' => [4, 1, 66, 16.5];
        yield 'four per booster among 64: whole odds stay a whole number' => [4, 1, 64, 16];
        yield 'two every eleven boosters among 3' => [2, 11, 3, 16.5];
        yield 'rounded to two decimals' => [3, 1, 10, 3.33];
        // More cards in a booster than the rarity has in the set.
        yield 'in every booster' => [4, 1, 3, 1];
    }

    public function testOddsForSpecificCardThrowsWhenNoCardOfThatRarityExists(): void
    {
        $game = new Game('Magic Test', 'magic-test-'.uniqid());
        $set = new CardSet($game, 'Kaldheim', 'KHM-'.uniqid());
        $mythic = new Rarity($game, 'Mythic Rare', 20);

        $this->em->persist($game);
        $this->em->persist($set);
        $this->em->persist($mythic);

        $pullRate = new PullRate($set, $mythic, 1, 8);
        $this->em->persist($pullRate);
        $this->em->flush();

        $this->expectException(\DomainException::class);

        $this->calculator->oddsForSpecificCard($pullRate);
    }
}
