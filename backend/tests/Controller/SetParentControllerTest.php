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
 * A set released in the boosters of another (a gallery, a classic
 * collection) is linked to it by an administrator, and then comes with it
 * wherever the main set is asked for.
 */
final class SetParentControllerTest extends AuthWebTestCase
{
    private Game $game;
    private CardSet $main;
    private CardSet $gallery;
    private CardSet $other;
    private Rarity $rare;
    private Rarity $classic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = new Game('Game '.uniqid(), 'game-'.uniqid());
        $this->main = new CardSet($this->game, 'Main Set', 'MAIN');
        $this->gallery = new CardSet($this->game, 'Main Set Gallery', 'MAIN-G');
        $this->other = new CardSet($this->game, 'Other Set', 'OTHER');
        $this->rare = new Rarity($this->game, 'Rare', 1);
        // A rarity only the gallery has.
        $this->classic = new Rarity($this->game, 'Classic', 2);
        foreach ([$this->game, $this->main, $this->gallery, $this->other, $this->rare, $this->classic] as $entity) {
            $this->em->persist($entity);
        }
        // The two sets number their cards alike, which is why they cannot be merged.
        $this->em->persist(new Card($this->main, 'Ember Wyrm', '001')->setRarity($this->rare));
        $this->em->persist(new Card($this->main, 'Frost Wyrm', '002')->setRarity($this->rare));
        $this->em->persist(new Card($this->gallery, 'Old Wyrm', '001')->setRarity($this->classic));
        $this->em->persist(new Card($this->gallery, 'Old Charm', '002')->setRarity($this->classic));
        $this->em->persist(new Card($this->other, 'Gust Charm', '001')->setRarity($this->rare));
        $this->em->flush();
    }

    public function testOnlyAnAdministratorLinksSets(): void
    {
        $uri = '/api/admin/sets/'.$this->gallery->getId().'/parent';
        $body = ['parentId' => (string) $this->main->getId()];

        $this->client->jsonRequest('PUT', $uri, $body);
        self::assertResponseStatusCodeSame(401);

        $this->client->jsonRequest('PUT', $uri, $body, $this->authorization($this->createUser()));
        self::assertResponseStatusCodeSame(403);
        self::assertNull($this->reloaded($this->gallery)->getParent());

        $this->link($this->gallery, $this->main);
        self::assertResponseIsSuccessful();
        self::assertSame(['parentCode' => 'MAIN'], $this->responseBody());
        self::assertSame('MAIN', $this->reloaded($this->gallery)->getParent()?->getCode());
    }

    /**
     * The point of the link: asking for the main set gives what its
     * boosters hold, sub-sets included.
     */
    public function testTheCardsOfASubSetComeWithItsMainSet(): void
    {
        $slug = $this->game->getSlug();

        $this->client->request('GET', '/api/cards?game='.$slug.'&set=MAIN');
        self::assertSame(2, $this->responseBody()['meta']['total']);

        $this->link($this->gallery, $this->main);

        $this->client->request('GET', '/api/cards?game='.$slug.'&set=MAIN');
        self::assertSame(4, $this->responseBody()['meta']['total']);
        self::assertSame(['Ember Wyrm', 'Frost Wyrm', 'Old Charm', 'Old Wyrm'], $this->sorted(array_column($this->responseBody()['data'], 'name')));

        // The sub-set can still be looked at alone.
        $this->client->request('GET', '/api/cards?game='.$slug.'&set=MAIN-G');
        self::assertSame(2, $this->responseBody()['meta']['total']);

        // The rarity filter of the main set offers those of the sub-set too.
        $this->client->request('GET', '/api/games/'.$slug.'/rarities?set=MAIN');
        self::assertSame(['Rare', 'Classic'], array_column($this->responseBody(), 'name'));

        // The list of sets says which one belongs to which.
        $this->client->request('GET', '/api/games/'.$slug.'/sets');
        self::assertSame(['MAIN' => null, 'MAIN-G' => 'MAIN', 'OTHER' => null], $this->sortedByKey(array_column($this->responseBody(), 'parentCode', 'code')));
    }

    /**
     * The cards of both sets come out of the same boosters: one table of
     * rates, on the main set, covers them all.
     */
    public function testThePullRatesOfAMainSetCoverItsSubSets(): void
    {
        $this->link($this->gallery, $this->main);
        $admin = $this->authorization($this->createAdmin());

        $this->client->jsonRequest('PUT', '/api/admin/sets/'.$this->main->getId().'/pull-rates', ['rates' => [
            (string) $this->rare->getId() => ['cards' => 1, 'boosters' => 3],
            (string) $this->classic->getId() => ['cards' => 1, 'boosters' => 10],
        ]], $admin);
        self::assertResponseIsSuccessful();
        $body = $this->responseBody();
        self::assertSame(['MAIN-G'], array_column($body['subSets'], 'code'));
        self::assertNull($body['parent']);
        self::assertSame(['Rare' => 2, 'Classic' => 2], array_column($body['rarities'], 'cardsInSet', 'name'));

        // A card of the sub-set: one Classic every ten boosters, two Classics.
        $card = $this->em->getRepository(Card::class)->findOneBy(['cardSet' => $this->gallery->getId(), 'numberInSet' => '001']);
        $this->client->request('GET', '/api/cards/'.$card?->getId());
        self::assertSame(20, $this->responseBody()['pullOddsOneIn']);

        // The sub-set says where its rates are, and takes none of its own.
        $this->client->request('GET', '/api/admin/sets/'.$this->gallery->getId().'/pull-rates', server: $admin);
        self::assertSame('MAIN', $this->responseBody()['parent']['code']);

        $this->client->jsonRequest('PUT', '/api/admin/sets/'.$this->gallery->getId().'/pull-rates', ['rates' => [(string) $this->classic->getId() => ['cards' => 1, 'boosters' => 2]]], $admin);
        self::assertResponseStatusCodeSame(409);
        self::assertSame(0, $this->em->getRepository(PullRate::class)->count(['cardSet' => $this->gallery->getId()]));
    }

    public function testASetStandsOnItsOwnAgainOnceUnlinked(): void
    {
        $this->link($this->gallery, $this->main);
        $this->link($this->gallery, null);

        self::assertResponseIsSuccessful();
        self::assertNull($this->reloaded($this->gallery)->getParent());
        $this->client->request('GET', '/api/cards?game='.$this->game->getSlug().'&set=MAIN');
        self::assertSame(2, $this->responseBody()['meta']['total']);
    }

    /**
     * Sets are linked on one level, inside one game.
     *
     * @param \Closure(self): array{CardSet, string} $case the set to link, and the id of the parent asked for
     */
    #[DataProvider('invalidLinks')]
    public function testRefusesALinkThatMakesNoSense(\Closure $case): void
    {
        [$set, $parentId] = $case($this);

        $this->client->jsonRequest('PUT', '/api/admin/sets/'.$set->getId().'/parent', ['parentId' => $parentId], $this->authorization($this->createAdmin()));

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->reloaded($set)->getParent());
    }

    /**
     * @return iterable<string, array{\Closure(self): array{CardSet, string}}>
     */
    public static function invalidLinks(): iterable
    {
        yield 'itself' => [static fn (self $test): array => [$test->other, (string) $test->other->getId()]];
        yield 'a set of another game' => [static fn (self $test): array => [$test->other, $test->setOfAnotherGame()]];
        yield 'a set that is itself a sub-set' => [static function (self $test): array {
            $test->link($test->gallery, $test->main);

            return [$test->other, (string) $test->gallery->getId()];
        }];
        yield 'for a set that has sub-sets' => [static function (self $test): array {
            $test->link($test->gallery, $test->main);

            return [$test->main, (string) $test->other->getId()];
        }];
        yield 'a set that does not exist' => [static fn (self $test): array => [$test->other, '01900000-0000-7000-8000-000000000000']];
        yield 'something that is not an id' => [static fn (self $test): array => [$test->other, 'main']];
    }

    private function setOfAnotherGame(): string
    {
        $game = new Game('Game '.uniqid(), 'game-'.uniqid());
        $set = new CardSet($game, 'Foreign', 'FOR');
        $this->em->persist($game);
        $this->em->persist($set);
        $this->em->flush();

        return (string) $set->getId();
    }

    private function link(CardSet $set, ?CardSet $parent): void
    {
        $this->client->jsonRequest('PUT', '/api/admin/sets/'.$set->getId().'/parent', ['parentId' => null === $parent ? null : (string) $parent->getId()], $this->authorization($this->createAdmin()));
    }

    private function reloaded(CardSet $set): CardSet
    {
        $this->em->clear();
        $found = $this->em->getRepository(CardSet::class)->find($set->getId());
        self::assertNotNull($found);

        return $found;
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

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }

    /**
     * @param array<string, ?string> $values
     *
     * @return array<string, ?string>
     */
    private function sortedByKey(array $values): array
    {
        ksort($values);

        return $values;
    }
}
