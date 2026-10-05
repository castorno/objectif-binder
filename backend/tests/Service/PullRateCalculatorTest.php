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

    public function testOddsForSpecificCardMultipliesRarityOddsByCardCount(): void
    {
        $game = new Game('Pokémon Test', 'pokemon-test-'.uniqid());
        $set = new CardSet($game, 'Paradox Rift', 'PAR-'.uniqid());
        $gold = new Rarity($game, 'Gold', 10);

        $this->em->persist($game);
        $this->em->persist($set);
        $this->em->persist($gold);

        foreach (['201', '202', '203'] as $number) {
            $card = new Card($set, "Gold Card {$number}", $number);
            $card->setRarity($gold);
            $this->em->persist($card);
        }

        $pullRate = new PullRate($set, $gold, 51);
        $this->em->persist($pullRate);
        $this->em->flush();

        self::assertSame(153, $this->calculator->oddsForSpecificCard($pullRate));
    }

    public function testOddsForSpecificCardThrowsWhenNoCardOfThatRarityExists(): void
    {
        $game = new Game('Magic Test', 'magic-test-'.uniqid());
        $set = new CardSet($game, 'Kaldheim', 'KHM-'.uniqid());
        $mythic = new Rarity($game, 'Mythic Rare', 20);

        $this->em->persist($game);
        $this->em->persist($set);
        $this->em->persist($mythic);

        $pullRate = new PullRate($set, $mythic, 8);
        $this->em->persist($pullRate);
        $this->em->flush();

        $this->expectException(\DomainException::class);

        $this->calculator->oddsForSpecificCard($pullRate);
    }
}
