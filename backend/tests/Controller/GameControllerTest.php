<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Card;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Entity\Rarity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GameControllerTest extends WebTestCase
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

    public function testListReturnsGamesOrderedByName(): void
    {
        $zelda = new Game('Zelda TCG', 'zelda-tcg-'.uniqid());
        $pokemon = new Game('Pokémon', 'pokemon-'.uniqid());
        $this->em->persist($zelda);
        $this->em->persist($pokemon);
        $this->em->flush();

        $this->client->request('GET', '/api/games');

        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $names = array_column($data, 'name');
        $pokemonIndex = array_search('Pokémon', $names, true);
        $zeldaIndex = array_search('Zelda TCG', $names, true);

        self::assertNotFalse($pokemonIndex);
        self::assertNotFalse($zeldaIndex);
        self::assertLessThan($zeldaIndex, $pokemonIndex);
    }

    public function testSetsReturnsOnlySetsOfTheGameMostRecentFirst(): void
    {
        $game = new Game('Pokémon', 'pokemon-'.uniqid());
        $otherGame = new Game('Magic', 'magic-'.uniqid());
        $this->em->persist($game);
        $this->em->persist($otherGame);

        $this->em->persist(new CardSet($game, 'Old Set', 'OLD')->setReleaseDate(new \DateTimeImmutable('2020-01-01')));
        $this->em->persist(new CardSet($game, 'New Set', 'NEW')->setReleaseDate(new \DateTimeImmutable('2024-01-01')));
        $this->em->persist(new CardSet($otherGame, 'Foreign Set', 'FOR'));
        $this->em->flush();

        $this->client->request('GET', '/api/games/'.$game->getSlug().'/sets');

        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame(['NEW', 'OLD'], array_column($data, 'code'));
        self::assertSame('2024-01-01', $data[0]['releaseDate']);
    }

    /**
     * What lets the list of sets mark those that will only show stand-ins.
     */
    public function testSetsSayWhetherAnyOfTheirCardsHasAPicture(): void
    {
        $game = new Game('Pokémon', 'pokemon-'.uniqid());
        $this->em->persist($game);
        $pictured = new CardSet($game, 'Pictured', 'PIC');
        $bare = new CardSet($game, 'Bare', 'BAR');
        $empty = new CardSet($game, 'Empty', 'EMP');
        $this->em->persist($pictured);
        $this->em->persist($bare);
        $this->em->persist($empty);
        // One picture is enough, even among cards without any.
        $this->em->persist(new Card($pictured, 'Ember Wyrm', '001')->setImageUrl('https://images.example.org/1.webp'));
        $this->em->persist(new Card($pictured, 'Frost Wyrm', '002'));
        $this->em->persist(new Card($bare, 'Gust Charm', '001'));
        $this->em->flush();

        $this->client->request('GET', '/api/games/'.$game->getSlug().'/sets');

        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(['BAR' => false, 'EMP' => false, 'PIC' => true], array_column($data, 'hasPictures', 'code'));
    }

    public function testRaritiesReturnsRaritiesOfTheGameBySortOrder(): void
    {
        $game = new Game('Pokémon', 'pokemon-'.uniqid());
        $this->em->persist($game);
        $this->em->persist(new Rarity($game, 'Gold', 10));
        $this->em->persist(new Rarity($game, 'Common', 1));
        $this->em->flush();

        $this->client->request('GET', '/api/games/'.$game->getSlug().'/rarities');

        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame(['Common', 'Gold'], array_column($data, 'name'));
    }

    public function testSetsReturns404ForUnknownGame(): void
    {
        $this->client->request('GET', '/api/games/does-not-exist/sets');

        self::assertResponseStatusCodeSame(404);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('error', $body);
    }
}
