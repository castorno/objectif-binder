<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Card;
use App\Entity\CardIdentity;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Entity\OwnedCard;

/**
 * The grouped catalog: the public list of identities, and what a signed-in
 * user owns of them.
 */
final class CardIdentityControllerTest extends AuthWebTestCase
{
    private Game $game;
    private CardSet $set;
    private int $cardNumber = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = new Game('Game '.uniqid(), 'game-'.uniqid());
        $this->set = new CardSet($this->game, 'Set', 'SET-'.uniqid());
        $this->em->persist($this->game);
        $this->em->persist($this->set);
    }

    public function testListIsPublicAndCountsTheCardsOfEachIdentity(): void
    {
        $pikachu = $this->persistIdentity('Pikachu', 25);
        $zekrom = $this->persistIdentity('Zekrom', 644);
        $this->persistIdentity('Bulbasaur', 1);
        $this->persistCard('Pikachu', $pikachu);
        $this->persistCard('Pikachu V', $pikachu);
        // Counts for both.
        $this->persistCard('Pikachu & Zekrom', $pikachu, $zekrom);
        $this->persistCard('Potion');
        $this->persistCard('Fire Energy');
        $this->em->flush();

        $this->client->request('GET', '/api/identities?game='.$this->game->getSlug());

        self::assertResponseIsSuccessful();
        $body = $this->responseBody();
        // In the game's own numbering.
        self::assertSame(['Bulbasaur', 'Pikachu', 'Zekrom'], array_column($body['data'], 'name'));
        self::assertSame([0, 3, 1], array_column($body['data'], 'cardCount'));
        self::assertSame(
            ['id' => (string) $pikachu->getId(), 'name' => 'Pikachu', 'sortOrder' => 25, 'gameSlug' => $this->game->getSlug(), 'cardCount' => 3],
            $body['data'][1],
        );
        self::assertSame(
            ['total' => 3, 'page' => 1, 'limit' => 20, 'totalPages' => 1, 'cardsWithoutIdentity' => 2],
            $body['meta'],
        );
    }

    public function testListSearchesByNameAndPaginates(): void
    {
        foreach (['Fire Wyrm' => 1, 'Ice Wyrm' => 2, 'Storm Wyrm' => 3, 'Fox' => 4] as $name => $order) {
            $this->persistIdentity($name, $order);
        }
        $this->em->flush();

        $this->client->request('GET', '/api/identities?'.http_build_query(['game' => $this->game->getSlug(), 'q' => 'WYRM', 'limit' => 2, 'page' => 2]));

        $body = $this->responseBody();
        self::assertSame(['Storm Wyrm'], array_column($body['data'], 'name'));
        self::assertSame(3, $body['meta']['total']);
        self::assertSame(2, $body['meta']['totalPages']);
    }

    public function testListPutsIdentitiesWithoutNumberAfterTheOthersByName(): void
    {
        $this->persistIdentity('Zebra', null);
        $this->persistIdentity('Numbered', 7);
        $this->persistIdentity('Ant', null);
        $this->em->flush();

        $this->client->request('GET', '/api/identities?game='.$this->game->getSlug());

        self::assertSame(['Numbered', 'Ant', 'Zebra'], array_column($this->responseBody()['data'], 'name'));
    }

    public function testListReturns422ForInvalidPagination(): void
    {
        $this->client->request('GET', '/api/identities?limit=0');

        self::assertResponseStatusCodeSame(422);
    }

    public function testShowReturnsOneIdentityAnd404ForAnUnknownOne(): void
    {
        $pikachu = $this->persistIdentity('Pikachu', 25);
        $this->em->flush();

        $this->client->request('GET', '/api/identities/'.$pikachu->getId());

        self::assertResponseIsSuccessful();
        self::assertSame(['id' => (string) $pikachu->getId(), 'name' => 'Pikachu', 'sortOrder' => 25], $this->responseBody());

        $this->client->request('GET', '/api/identities/01996a2e-0000-7000-8000-000000000000');

        self::assertResponseStatusCodeSame(404);
    }

    public function testOwnedCountsRequireAuthentication(): void
    {
        $this->client->request('GET', '/api/collection/identities');

        self::assertResponseStatusCodeSame(401);
    }

    public function testOwnedCountsCardsOncePerIdentityWhateverTheLanguages(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();
        $pikachu = $this->persistIdentity('Pikachu', 25);
        $zekrom = $this->persistIdentity('Zekrom', 644);
        $this->persistIdentity('Bulbasaur', 1);
        $first = $this->persistCard('Pikachu', $pikachu);
        $this->persistCard('Pikachu V', $pikachu);
        $both = $this->persistCard('Pikachu & Zekrom', $pikachu, $zekrom);
        $potion = $this->persistCard('Potion');
        $this->persistCard('Fire Energy');
        // One card in two languages is one card owned.
        $this->em->persist(new OwnedCard($user, $first, 'fr', 3));
        $this->em->persist(new OwnedCard($user, $first, 'ja'));
        $this->em->persist(new OwnedCard($user, $both, 'fr'));
        $this->em->persist(new OwnedCard($user, $potion, 'fr'));
        // Someone else's cards do not count.
        $this->em->persist(new OwnedCard($other, $this->persistCard('Pikachu ex', $pikachu), 'fr'));
        $this->em->flush();
        $this->login($user->getEmail());
        $token = $this->responseBody()['token'];

        $this->client->request('GET', '/api/collection/identities?game='.$this->game->getSlug(), server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertResponseIsSuccessful();
        self::assertEquals(
            [
                // Three identities, two of them started: nothing owned of Bulbasaur.
                'totalIdentities' => 3,
                'startedIdentities' => 2,
                'ownedByIdentity' => [(string) $pikachu->getId() => 2, (string) $zekrom->getId() => 1],
                'ownedWithoutIdentity' => 1,
            ],
            $this->responseBody(),
        );
    }

    public function testOwnedCountsOfSomeoneOwningNothingIsAnEmptyObject(): void
    {
        $user = $this->createUser();
        $this->persistIdentity('Pikachu', 25);
        $this->em->flush();
        $this->login($user->getEmail());
        $token = $this->responseBody()['token'];

        $this->client->request('GET', '/api/collection/identities?game='.$this->game->getSlug(), server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);

        self::assertSame(
            '{"totalIdentities":1,"startedIdentities":0,"ownedByIdentity":{},"ownedWithoutIdentity":0}',
            $this->client->getResponse()->getContent(),
        );
    }

    public function testStartedIdentitiesFollowTheSearchAndNotThePage(): void
    {
        $user = $this->createUser();
        foreach (['Fire Wyrm' => 1, 'Ice Wyrm' => 2, 'Storm Wyrm' => 3, 'Fox' => 4] as $name => $order) {
            $identity = $this->persistIdentity($name, $order);
            $card = $this->persistCard($name, $identity);
            if ('Ice Wyrm' !== $name) {
                $this->em->persist(new OwnedCard($user, $card, 'fr'));
            }
        }
        $this->em->flush();
        $this->login($user->getEmail());
        $token = $this->responseBody()['token'];

        // A page of one entry: the counts still cover every wyrm, and leave the fox out.
        $this->client->request(
            'GET',
            '/api/collection/identities?'.http_build_query(['game' => $this->game->getSlug(), 'q' => 'wyrm', 'limit' => 1]),
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token],
        );

        $body = $this->responseBody();
        self::assertSame(3, $body['totalIdentities']);
        self::assertSame(2, $body['startedIdentities']);
        self::assertCount(1, $body['ownedByIdentity']);
    }

    private function persistIdentity(string $name, ?int $sortOrder): CardIdentity
    {
        $identity = new CardIdentity($this->game, $name, 'identity-'.uniqid())->setSortOrder($sortOrder);
        $this->em->persist($identity);

        return $identity;
    }

    private function persistCard(string $name, CardIdentity ...$identities): Card
    {
        $card = new Card($this->set, $name, sprintf('%03d', ++$this->cardNumber));
        foreach ($identities as $identity) {
            $card->addIdentity($identity);
        }
        $this->em->persist($card);

        return $card;
    }
}
