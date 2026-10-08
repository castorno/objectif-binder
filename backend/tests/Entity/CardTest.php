<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Card;
use App\Entity\CardIdentity;
use App\Entity\CardSet;
use App\Entity\Game;
use PHPUnit\Framework\TestCase;

final class CardTest extends TestCase
{
    public function testACardCanHaveSeveralIdentitiesAndKeepsEachOnce(): void
    {
        $game = new Game('Pokémon', 'pokemon');
        $card = new Card(new CardSet($game, 'Base Set', 'BS'), 'Pikachu & Zekrom', '001');
        $pikachu = new CardIdentity($game, 'Pikachu', '25');
        $zekrom = new CardIdentity($game, 'Zekrom', '644');

        $card->addIdentity($pikachu)->addIdentity($zekrom)->addIdentity($pikachu);

        self::assertSame([$pikachu, $zekrom], $card->getIdentities()->getValues());
    }

    public function testACardRefusesAnIdentityOfAnotherGame(): void
    {
        $card = new Card(new CardSet(new Game('Pokémon', 'pokemon'), 'Base Set', 'BS'), 'Pikachu', '001');
        $identityOfAnotherGame = new CardIdentity(new Game('Magic', 'magic'), 'Lightning Bolt', 'oracle-1');

        $this->expectException(\InvalidArgumentException::class);

        $card->addIdentity($identityOfAnotherGame);
    }
}
