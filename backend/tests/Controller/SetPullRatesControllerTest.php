<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Card;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Entity\PullRate;
use App\Entity\Rarity;
use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Pull rates entered by an administrator, one figure per rarity of a set.
 */
final class SetPullRatesControllerTest extends AuthWebTestCase
{
    private Game $game;
    private CardSet $set;
    private Rarity $common;
    private Rarity $rare;
    private Rarity $gold;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = new Game('Game '.uniqid(), 'game-'.uniqid());
        $this->set = new CardSet($this->game, 'First Set', 'ONE');
        $this->common = new Rarity($this->game, 'Common', 1);
        $this->rare = new Rarity($this->game, 'Rare', 2);
        // A rarity of the game that no card of the set has.
        $this->gold = new Rarity($this->game, 'Gold', 3);
        foreach ([$this->game, $this->set, $this->common, $this->rare, $this->gold] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->persist(new Card($this->set, 'Ember Wyrm', '001')->setRarity($this->rare));
        $this->em->persist(new Card($this->set, 'Frost Wyrm', '002')->setRarity($this->rare));
        $this->em->persist(new Card($this->set, 'Gust Charm', '003')->setRarity($this->common));
        // Without rarity: nothing to give a rate to.
        $this->em->persist(new Card($this->set, 'Token', '004'));
        $this->em->flush();
    }

    /**
     * The role is what opens these routes: being signed in is not enough.
     */
    public function testOnlyAnAdministratorReadsOrWritesPullRates(): void
    {
        $uri = '/api/admin/sets/'.$this->set->getId().'/pull-rates';

        $this->client->request('GET', $uri);
        self::assertResponseStatusCodeSame(401);

        $user = $this->authorization($this->createUser());
        $this->client->request('GET', $uri, server: $user);
        self::assertResponseStatusCodeSame(403);
        $this->client->jsonRequest('PUT', $uri, ['rates' => [(string) $this->rare->getId() => ['cards' => 1, 'boosters' => 5]]], $user);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, $this->em->getRepository(PullRate::class)->count(['cardSet' => $this->set]));

        $this->client->request('GET', $uri, server: $this->authorization($this->createAdmin()));
        self::assertResponseIsSuccessful();
    }

    public function testListsTheRaritiesOfTheCardsOfTheSetWithTheirRate(): void
    {
        $this->em->persist(new PullRate($this->set, $this->rare, 1, 6, 'Opening of 1,000 boosters'));
        $this->em->flush();

        $this->get();

        self::assertResponseIsSuccessful();
        $body = $this->responseBody();
        self::assertSame('ONE', $body['set']['code']);
        self::assertSame('Opening of 1,000 boosters', $body['source']);
        self::assertNotNull($body['updatedAt']);
        // In the order of the rarities; Gold has no card here and no rate.
        self::assertSame(
            [
                ['name' => 'Common', 'cardsInSet' => 1, 'rate' => null],
                ['name' => 'Rare', 'cardsInSet' => 2, 'rate' => ['cards' => 1, 'boosters' => 6]],
            ],
            array_map(static fn (array $rarity): array => array_diff_key($rarity, ['id' => true]), $body['rarities']),
        );
    }

    /**
     * The body is the whole state of the set: what is in it is created or
     * changed, what is not is deleted.
     */
    public function testReplacesTheRatesOfTheSet(): void
    {
        // Four commons in every booster, a rare every six boosters.
        $this->put(['source' => '  First estimate ', 'rates' => [(string) $this->common->getId() => ['cards' => 4, 'boosters' => 1], (string) $this->rare->getId() => ['cards' => 1, 'boosters' => 6]]]);
        self::assertResponseIsSuccessful();
        self::assertSame(['Common' => '4 for 1', 'Rare' => '1 for 6'], $this->storedRates());
        // What was saved comes back, ready to be shown again.
        self::assertSame([['cards' => 4, 'boosters' => 1], ['cards' => 1, 'boosters' => 6]], array_column($this->responseBody()['rarities'], 'rate'));
        self::assertSame('First estimate', $this->responseBody()['source']);

        $this->put(['source' => 'Second estimate', 'rates' => [(string) $this->rare->getId() => ['cards' => 2, 'boosters' => 11]]]);
        self::assertResponseIsSuccessful();
        self::assertSame(['Rare' => '2 for 11'], $this->storedRates());
        self::assertSame('Second estimate', $this->responseBody()['source']);

        $this->put(['rates' => []]);
        self::assertSame([], $this->storedRates());
    }

    /**
     * The card page works out the odds of one card from the rate of its
     * rarity, and says where the figure comes from.
     */
    public function testACardShowsTheOddsEnteredAndTheirSource(): void
    {
        $this->put(['source' => 'Opening of 1,000 boosters', 'rates' => [
            (string) $this->rare->getId() => ['cards' => 1, 'boosters' => 6],
            (string) $this->common->getId() => ['cards' => 4, 'boosters' => 1],
        ]]);
        $cards = $this->em->getRepository(Card::class);

        $this->client->request('GET', '/api/cards/'.$cards->findOneBy(['cardSet' => $this->set, 'numberInSet' => '001'])?->getId());
        // 1 in 6 for a Rare, and two Rares in the set.
        self::assertSame(12, $this->responseBody()['pullOddsOneIn']);
        self::assertSame('Opening of 1,000 boosters', $this->responseBody()['pullOddsSource']);

        $this->client->request('GET', '/api/cards/'.$cards->findOneBy(['cardSet' => $this->set, 'numberInSet' => '003'])?->getId());
        // Four commons per booster and a single Common in the set: in every booster.
        self::assertSame(1, $this->responseBody()['pullOddsOneIn']);
    }

    public function testARateStaysWithinReachAfterTheCardsOfItsRarityAreGone(): void
    {
        $this->em->persist(new PullRate($this->set, $this->gold, 1, 50));
        $this->em->flush();

        $this->get();

        self::assertContains(['name' => 'Gold', 'cardsInSet' => 0, 'rate' => ['cards' => 1, 'boosters' => 50]], array_map(
            static fn (array $rarity): array => array_diff_key($rarity, ['id' => true]),
            $this->responseBody()['rarities'],
        ));
    }

    /**
     * @param \Closure(self): array<string, mixed> $payload
     */
    #[DataProvider('invalidPayloads')]
    public function testRefusesFiguresThatMakeNoSenseAndChangesNothing(\Closure $payload): void
    {
        $this->em->persist(new PullRate($this->set, $this->rare, 1, 6));
        $this->em->flush();

        $this->put($payload($this));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(['Rare' => '1 for 6'], $this->storedRates());
    }

    /**
     * @return iterable<string, array{\Closure(self): array<string, mixed>}>
     */
    public static function invalidPayloads(): iterable
    {
        $rare = static fn (self $test, mixed $rate): array => ['rates' => [(string) $test->rare->getId() => $rate]];

        yield 'no booster' => [static fn (self $test): array => $rare($test, ['cards' => 1, 'boosters' => 0])];
        yield 'no card' => [static fn (self $test): array => $rare($test, ['cards' => 0, 'boosters' => 6])];
        yield 'a negative number' => [static fn (self $test): array => $rare($test, ['cards' => 1, 'boosters' => -4])];
        yield 'not a whole number' => [static fn (self $test): array => $rare($test, ['cards' => 1, 'boosters' => 'often'])];
        yield 'half a card' => [static fn (self $test): array => $rare($test, ['cards' => 0.5, 'boosters' => 1])];
        yield 'boosters left out' => [static fn (self $test): array => $rare($test, ['cards' => 1])];
        yield 'a bare number' => [static fn (self $test): array => $rare($test, 6)];
        yield 'something that is not a rarity' => [static fn (self $test): array => ['rates' => ['not-a-rarity' => ['cards' => 1, 'boosters' => 6]]]];
        yield 'a rarity of another game' => [static fn (self $test): array => ['rates' => [$test->rarityOfAnotherGame() => ['cards' => 1, 'boosters' => 6]]]];
        yield 'a source too long' => [static fn (self $test): array => ['source' => str_repeat('a', 256)] + $rare($test, ['cards' => 1, 'boosters' => 6])];
    }

    public function testAnswers404ForASetThatDoesNotExist(): void
    {
        $this->client->request('GET', '/api/admin/sets/01900000-0000-7000-8000-000000000000/pull-rates', server: $this->authorization($this->createAdmin()));

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * What lets the interface show the way to the administration. The API
     * never relies on it: each route checks the role.
     */
    public function testAnAccountSaysWhetherItIsAnAdministrator(): void
    {
        $this->client->request('GET', '/api/me', server: $this->authorization($this->createUser()));
        self::assertFalse($this->responseBody()['isAdmin']);

        $this->client->request('GET', '/api/me', server: $this->authorization($this->createAdmin()));
        self::assertTrue($this->responseBody()['isAdmin']);
        self::assertArrayNotHasKey('roles', $this->responseBody());
    }

    private function rarityOfAnotherGame(): string
    {
        $game = new Game('Game '.uniqid(), 'game-'.uniqid());
        $rarity = new Rarity($game, 'Rare', 1);
        $this->em->persist($game);
        $this->em->persist($rarity);
        $this->em->flush();

        return (string) $rarity->getId();
    }

    private function createAdmin(): User
    {
        $admin = $this->createUser()->setRoles([User::ROLE_ADMIN]);
        $this->em->flush();

        return $admin;
    }

    /**
     * @return array<string, string>
     */
    private function authorization(User $user): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.static::getContainer()->get(JWTTokenManagerInterface::class)->create($user)];
    }

    private function get(): void
    {
        $this->client->request('GET', '/api/admin/sets/'.$this->set->getId().'/pull-rates', server: $this->authorization($this->createAdmin()));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function put(array $payload): void
    {
        $this->client->jsonRequest('PUT', '/api/admin/sets/'.$this->set->getId().'/pull-rates', $payload, $this->authorization($this->createAdmin()));
    }

    /**
     * @return array<string, string> "N for M" by rarity name, as the database has them
     */
    private function storedRates(): array
    {
        $this->em->clear();

        $rates = [];
        foreach ($this->em->getRepository(PullRate::class)->findBy(['cardSet' => $this->set->getId()]) as $pullRate) {
            $rates[$pullRate->getRarity()->getName()] = $pullRate->getCardCount().' for '.$pullRate->getBoosterCount();
        }
        ksort($rates);

        return $rates;
    }
}
