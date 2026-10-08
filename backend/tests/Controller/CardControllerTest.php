<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Card;
use App\Entity\CardIdentity;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Entity\PullRate;
use App\Entity\Rarity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CardControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Several requests per test share one kernel, hence the connection
        // whose transaction holds what the test created.
        $this->client->disableReboot();
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

    /**
     * "%" and "_" mean "anything" in a SQL LIKE: typed by a user, they must
     * be looked for as plain characters, not match every card.
     */
    public function testListSearchesLikeWildcardsAsPlainCharacters(): void
    {
        $game = $this->persistGame('Wildcards', 'wildcards-'.uniqid());
        $set = $this->persistSet($game, 'Base Set', 'BS-'.uniqid());

        $this->persistCard($set, 'Charizard', '001');
        $this->persistCard($set, '100% Foil', '002');
        $this->persistCard($set, 'Proto_Type', '003');
        $this->persistCard($set, 'Back\\Slash', '004');
        $this->em->flush();

        foreach (['%' => '100% Foil', '_' => 'Proto_Type', '\\' => 'Back\\Slash', 'o_t' => 'Proto_Type'] as $search => $expected) {
            $this->client->request('GET', '/api/cards?'.http_build_query(['game' => $game->getSlug(), 'q' => $search]));

            self::assertResponseIsSuccessful();
            $body = json_decode($this->client->getResponse()->getContent(), true);

            self::assertSame([$expected], array_column($body['data'], 'name'), sprintf('Searching for "%s"', $search));
        }
    }

    /**
     * Sets come in the order they were released, whatever their codes; a
     * set whose date is unknown comes last.
     */
    public function testListSortsCardsByTheReleaseDateOfTheirSet(): void
    {
        $game = $this->persistGame('Dates', 'dates-'.uniqid());
        $undated = $this->persistSet($game, 'Undated', 'AAA-'.uniqid());
        $recent = $this->persistSet($game, 'Recent', 'BBB-'.uniqid())->setReleaseDate(new \DateTimeImmutable('2024-05-01'));
        $old = $this->persistSet($game, 'Old', 'ZZZ-'.uniqid())->setReleaseDate(new \DateTimeImmutable('1999-01-09'));

        $this->persistCard($undated, 'Undated 1', '1');
        $this->persistCard($recent, 'Recent 2', '2');
        $this->persistCard($recent, 'Recent 1', '1');
        $this->persistCard($old, 'Old 1', '1');
        $this->em->flush();

        $this->client->request('GET', '/api/cards?game='.$game->getSlug());
        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame(['Old 1', 'Recent 1', 'Recent 2', 'Undated 1'], array_column($body['data'], 'name'));
    }

    public function testShowGivesTheReleaseDateOfTheSet(): void
    {
        $game = $this->persistGame('Dates', 'dates-'.uniqid());
        $dated = $this->persistCard($this->persistSet($game, 'Old', 'OLD-'.uniqid())->setReleaseDate(new \DateTimeImmutable('1999-01-09')), 'Old 1', '1');
        $undated = $this->persistCard($this->persistSet($game, 'Undated', 'UND-'.uniqid()), 'Undated 1', '1');
        $this->em->flush();

        $this->client->request('GET', '/api/cards/'.$dated->getId());
        self::assertSame('1999-01-09', json_decode($this->client->getResponse()->getContent(), true)['setReleaseDate']);

        $this->client->request('GET', '/api/cards/'.$undated->getId());
        self::assertNull(json_decode($this->client->getResponse()->getContent(), true)['setReleaseDate']);
    }

    /**
     * A card number is text ("TG02", "4a"), but people read the digits in
     * it as numbers: 2 comes before 10. Plain text order says the opposite.
     */
    public function testListSortsCardNumbersTheWayAPersonReadsThem(): void
    {
        $game = $this->persistGame('Numbers', 'numbers-'.uniqid());
        $set = $this->persistSet($game, 'Base Set', 'BS-'.uniqid());

        foreach (['10', '2', 'TG10', '1', '100', '4a', 'TG02', '9'] as $number) {
            $this->persistCard($set, 'Card '.$number, $number);
        }
        $this->em->flush();

        $this->client->request('GET', '/api/cards?game='.$game->getSlug());
        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame(['1', '2', '4a', '9', '10', '100', 'TG02', 'TG10'], array_column($body['data'], 'numberInSet'));
    }

    /**
     * The sort rule must not change what counts as the same number: "1" and
     * "01" are two cards, and the unique index must keep telling them apart.
     */
    public function testNumbersThatOnlyDifferByLeadingZerosAreDifferentCards(): void
    {
        $game = $this->persistGame('Numbers', 'numbers-'.uniqid());
        $set = $this->persistSet($game, 'Base Set', 'BS-'.uniqid());

        $this->persistCard($set, 'One', '1');
        $this->persistCard($set, 'Zero one', '01');
        $this->em->flush();

        $this->client->request('GET', '/api/cards?game='.$game->getSlug());
        $body = json_decode($this->client->getResponse()->getContent(), true);

        self::assertSame(2, $body['meta']['total']);
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

    public function testListFiltersByIdentity(): void
    {
        $game = $this->persistGame('Pokémon', 'pokemon-'.uniqid());
        $set = $this->persistSet($game, 'Base Set', 'BS-'.uniqid());
        $pikachu = $this->persistIdentity($game, 'Pikachu', 25);
        $zekrom = $this->persistIdentity($game, 'Zekrom', 644);
        $this->persistCard($set, 'Pikachu', '001')->addIdentity($pikachu);
        $this->persistCard($set, 'Pikachu V', '002')->addIdentity($pikachu);
        // A card showing two of them belongs to both, and is listed once in each.
        $this->persistCard($set, 'Pikachu & Zekrom', '003')->addIdentity($pikachu)->addIdentity($zekrom);
        $this->persistCard($set, 'Potion', '004');
        $this->em->flush();

        $this->client->request('GET', '/api/cards?identity='.$pikachu->getId());

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(['Pikachu', 'Pikachu V', 'Pikachu & Zekrom'], array_column($body['data'], 'name'));
        self::assertSame(3, $body['meta']['total']);

        $this->client->request('GET', '/api/cards?identity='.$zekrom->getId());

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(['Pikachu & Zekrom'], array_column($body['data'], 'name'));
    }

    public function testListFiltersCardsWithoutIdentity(): void
    {
        $game = $this->persistGame('Pokémon', 'pokemon-'.uniqid());
        $set = $this->persistSet($game, 'Base Set', 'BS-'.uniqid());
        $this->persistCard($set, 'Pikachu', '001')->addIdentity($this->persistIdentity($game, 'Pikachu', 25));
        $this->persistCard($set, 'Potion', '002');
        $this->persistCard($set, 'Fire Energy', '003');
        $this->em->flush();

        $this->client->request('GET', '/api/cards?'.http_build_query(['identity' => 'none', 'set' => $set->getCode()]));

        self::assertResponseIsSuccessful();
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(['Potion', 'Fire Energy'], array_column($body['data'], 'name'));
    }

    public function testListReturns422ForAnIdentityThatIsNotAnId(): void
    {
        $this->client->request('GET', '/api/cards?identity=pikachu');

        self::assertResponseStatusCodeSame(422);
        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(['This value should be the id of an identity, or "none".'], $body['violations']['identity']);
    }

    public function testListIsEmptyForAnUnknownIdentity(): void
    {
        $this->client->request('GET', '/api/cards?identity=01996a2e-0000-7000-8000-000000000000');

        self::assertResponseIsSuccessful();
        self::assertSame([], json_decode($this->client->getResponse()->getContent(), true)['data']);
    }

    public function testShowListsTheIdentitiesOfTheCard(): void
    {
        $game = $this->persistGame('Pokémon', 'pokemon-'.uniqid());
        $set = $this->persistSet($game, 'Base Set', 'BS-'.uniqid());
        $pikachu = $this->persistIdentity($game, 'Pikachu', 25);
        $card = $this->persistCard($set, 'Pikachu', '001')->addIdentity($pikachu);
        $this->em->flush();

        $this->client->request('GET', '/api/cards/'.$card->getId());

        $body = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame([['id' => (string) $pikachu->getId(), 'name' => 'Pikachu', 'sortOrder' => 25]], $body['identities']);
    }

    /**
     * Guards against the "N+1" trap: loading the set, game and rarity of each
     * card with a query of its own. However many cards a page holds, and
     * however many sets they come from, the number of queries must not move.
     */
    public function testListRunsTheSameNumberOfQueriesWhateverThePageSize(): void
    {
        $marker = 'nplus1-'.uniqid();
        // Every card in its own game, set and rarity: the worst case.
        for ($i = 1; $i <= 8; ++$i) {
            $game = $this->persistGame("Game {$i} {$marker}", "game-{$i}-{$marker}");
            $set = $this->persistSet($game, "Set {$i}", "SET-{$i}-{$marker}");
            $this->persistCard($set, "Card {$marker} {$i}", '001', $this->persistRarity($game, 'Rare'));
        }
        $this->em->flush();

        $queriesForTwoCards = $this->countQueriesOfList(['q' => $marker, 'limit' => 2], expectedCards: 2);
        $queriesForEightCards = $this->countQueriesOfList(['q' => $marker, 'limit' => 8], expectedCards: 8);

        self::assertSame($queriesForTwoCards, $queriesForEightCards);
        // One query for the page, one for the total.
        self::assertSame(2, $queriesForEightCards);
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

    /**
     * Looking at a price may send a request to the service providing it:
     * the route is not open to everyone, unlike the rest of the catalog.
     */
    public function testPriceIsForSignedInUsersOnly(): void
    {
        $game = $this->persistGame('Prices', 'prices-'.uniqid());
        $card = $this->persistCard($this->persistSet($game, 'Base Set', 'BS-'.uniqid()), 'Charizard', '004');
        $this->em->flush();

        $this->client->request('GET', '/api/cards/'.$card->getId().'/price');

        self::assertResponseStatusCodeSame(401);
        // The card itself stays public.
        $this->client->request('GET', '/api/cards/'.$card->getId());
        self::assertResponseIsSuccessful();
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

    /**
     * @param array<string, scalar> $parameters
     */
    private function countQueriesOfList(array $parameters, int $expectedCards): int
    {
        // Forget what the test just created: entities already in memory would
        // need no query, and hide the very problem this looks for.
        $this->em->clear();
        /** @var DebugDataHolder $queries */
        $queries = static::getContainer()->get('doctrine.debug_data_holder');
        $queries->reset();

        $this->client->request('GET', '/api/cards?'.http_build_query($parameters));

        self::assertResponseIsSuccessful();
        self::assertCount($expectedCards, json_decode($this->client->getResponse()->getContent(), true)['data']);

        return \count($queries->getData()['default'] ?? []);
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

    private function persistIdentity(Game $game, string $name, int $sortOrder): CardIdentity
    {
        $identity = new CardIdentity($game, $name, 'identity-'.$sortOrder)->setSortOrder($sortOrder);
        $this->em->persist($identity);

        return $identity;
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
