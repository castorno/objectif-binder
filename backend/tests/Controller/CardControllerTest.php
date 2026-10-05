<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Card;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Entity\PullRate;
use App\Entity\Rarity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CardControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        $this->em->close();

        parent::tearDown();
    }

    public function testListFiltersByGameSlug(): void
    {
        $pokemon = $this->persistGame('Pokémon', 'pokemon-'.uniqid());
        $magic = $this->persistGame('Magic', 'magic-'.uniqid());

        $pokemonSet = $this->persistSet($pokemon, 'Paradox Rift', 'PAR-'.uniqid());
        $magicSet = $this->persistSet($magic, 'Kaldheim', 'KHM-'.uniqid());

        $this->persistCard($pokemonSet, 'Charizard', '001');
        $this->persistCard($magicSet, 'Black Lotus', '001');
        $this->em->flush();

        $this->client->request('GET', '/api/cards?game='.$pokemon->getSlug());

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertCount(1, $body['data']);
        self::assertSame('Charizard', $body['data'][0]['name']);
    }

    public function testListFiltersByNameSearchCaseInsensitive(): void
    {
        $game = $this->persistGame('Pokémon', 'pokemon-'.uniqid());
        $set = $this->persistSet($game, 'Base Set', 'BS-'.uniqid());

        $this->persistCard($set, 'Charizard', '004');
        $this->persistCard($set, 'Blastoise', '002');
        $this->em->flush();

        $this->client->request('GET', '/api/cards?q=char');

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertCount(1, $body['data']);
        self::assertSame('Charizard', $body['data'][0]['name']);
    }

    public function testListPaginatesResults(): void
    {
        $game = $this->persistGame('Pokémon', 'pokemon-'.uniqid());
        $set = $this->persistSet($game, 'Base Set', 'BS-'.uniqid());

        for ($i = 1; $i <= 5; ++$i) {
            $this->persistCard($set, "Card {$i}", (string) $i);
        }
        $this->em->flush();

        $this->client->request('GET', '/api/cards?'.http_build_query(['set' => $set->getCode(), 'limit' => 2, 'page' => 2]));

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertCount(2, $body['data']);
        self::assertSame(5, $body['meta']['total']);
        self::assertSame(2, $body['meta']['page']);
        self::assertSame(3, $body['meta']['totalPages']);
    }

    public function testListReturns422ForInvalidLimit(): void
    {
        $this->client->request('GET', '/api/cards?limit=0');

        self::assertResponseStatusCodeSame(422);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $body);
    }

    public function testShowReturnsCardDetailWithComputedPullOdds(): void
    {
        $game = $this->persistGame('Pokémon', 'pokemon-'.uniqid());
        $set = $this->persistSet($game, 'Paradox Rift', 'PAR-'.uniqid());
        $gold = $this->persistRarity($game, 'Gold', 10);

        $card = $this->persistCard($set, 'Gold Card 1', '201', $gold);
        $this->persistCard($set, 'Gold Card 2', '202', $gold);
        $this->persistCard($set, 'Gold Card 3', '203', $gold);

        $pullRate = new PullRate($set, $gold, 51);
        $this->em->persist($pullRate);
        $this->em->flush();

        $this->client->request('GET', '/api/cards/'.$card->getId());

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame('Gold Card 1', $body['name']);
        self::assertSame(153, $body['pullOddsOneIn']);
    }

    public function testShowReturnsNullPullOddsWhenNoPullRateIsConfigured(): void
    {
        $game = $this->persistGame('Pokémon', 'pokemon-'.uniqid());
        $set = $this->persistSet($game, 'Paradox Rift', 'PAR-'.uniqid());
        $card = $this->persistCard($set, 'Common Card', '001');
        $this->em->flush();

        $this->client->request('GET', '/api/cards/'.$card->getId());

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertNull($body['pullOddsOneIn']);
    }

    public function testShowReturns404ForUnknownId(): void
    {
        $this->client->request('GET', '/api/cards/01996a2e-0000-7000-8000-000000000000');

        self::assertResponseStatusCodeSame(404);
    }

    public function testShowReturns404ForMalformedId(): void
    {
        $this->client->request('GET', '/api/cards/not-a-uuid');

        self::assertResponseStatusCodeSame(404);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $body);
    }

    private function persistGame(string $name, string $slug): Game
    {
        $game = new Game($name, $slug);
        $this->em->persist($game);

        return $game;
    }

    private function persistSet(Game $game, string $name, string $code): CardSet
    {
        $set = new CardSet($game, $name, $code);
        $this->em->persist($set);

        return $set;
    }

    private function persistRarity(Game $game, string $name, int $sortOrder = 0): Rarity
    {
        $rarity = new Rarity($game, $name, $sortOrder);
        $this->em->persist($rarity);

        return $rarity;
    }

    private function persistCard(CardSet $set, string $name, string $number, ?Rarity $rarity = null): Card
    {
        $card = new Card($set, $name, $number);
        if (null !== $rarity) {
            $card->setRarity($rarity);
        }
        $this->em->persist($card);

        return $card;
    }
}
