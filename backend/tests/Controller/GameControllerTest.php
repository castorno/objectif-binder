<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Game;
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
}
