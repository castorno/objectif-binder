<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Card;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Entity\OwnedCard;
use App\Entity\User;
use App\Enum\CardCondition;

final class CollectionControllerTest extends AuthWebTestCase
{
    private const string UNKNOWN_CARD_ID = '01996a2e-0000-7000-8000-000000000000';

    public function testEveryCollectionRouteRequiresAuthentication(): void
    {
        $card = $this->persistCard('Charizard', '004');

        $this->client->request('GET', '/api/collection');
        self::assertResponseStatusCodeSame(401);

        $this->client->request('GET', '/api/collection/cards/'.$card->getId());
        self::assertResponseStatusCodeSame(401);

        $this->client->request('GET', '/api/collection/completion');
        self::assertResponseStatusCodeSame(401);

        $this->client->jsonRequest('PUT', '/api/collection/cards/'.$card->getId().'/fr', ['quantity' => 1]);
        self::assertResponseStatusCodeSame(401);

        $this->client->request('DELETE', '/api/collection/cards/'.$card->getId().'/fr');
        self::assertResponseStatusCodeSame(401);

        self::assertSame(0, $this->countOwnedCards());
    }

    public function testPutAddsACardToTheCollection(): void
    {
        $user = $this->createUser();
        $card = $this->persistCard('Charizard', '004');
        $token = $this->tokenFor($user);

        $this->put($token, $card, 'fr', ['quantity' => 2, 'condition' => 'near_mint']);

        self::assertResponseStatusCodeSame(201);
        $body = $this->responseBody();
        self::assertSame('fr', $body['language']);
        self::assertSame(2, $body['quantity']);
        self::assertSame('near_mint', $body['condition']);

        $stored = $this->findOwnedCard($user, $card, 'fr');
        self::assertNotNull($stored);
        self::assertSame(2, $stored->getQuantity());
        self::assertSame(CardCondition::NearMint, $stored->getCondition());
    }

    public function testPutTwiceUpdatesTheSameEntryInsteadOfDuplicatingIt(): void
    {
        $user = $this->createUser();
        $card = $this->persistCard('Charizard', '004');
        $token = $this->tokenFor($user);

        $this->put($token, $card, 'fr', ['quantity' => 1, 'condition' => 'mint']);
        $this->put($token, $card, 'fr', ['quantity' => 3]);

        // 200, not 201: the entry already existed.
        self::assertResponseStatusCodeSame(200);
        self::assertSame(3, $this->responseBody()['quantity']);
        // The body is the whole new state: a condition left out is cleared.
        self::assertNull($this->responseBody()['condition']);
        self::assertSame(1, $this->countOwnedCards());
    }

    public function testTheSameCardCanBeOwnedInSeveralLanguages(): void
    {
        $user = $this->createUser();
        $card = $this->persistCard('Charizard', '004');
        $token = $this->tokenFor($user);

        $this->put($token, $card, 'fr', ['quantity' => 1]);
        $this->put($token, $card, 'ja', ['quantity' => 4]);

        $this->get($token, '/api/collection/cards/'.$card->getId());

        self::assertResponseIsSuccessful();
        $entries = $this->responseBody()['data'];
        self::assertSame(['fr', 'ja'], array_column($entries, 'language'));
        self::assertSame([1, 4], array_column($entries, 'quantity'));
    }

    public function testListGroupsTheLanguagesOfACardIntoOneItem(): void
    {
        $user = $this->createUser();
        $token = $this->tokenFor($user);
        $set = $this->persistSet();
        $charizard = $this->persistCard('Charizard', '004', $set);
        $this->persistOwnedCard($user, $charizard, 'ja', 1);
        $this->persistOwnedCard($user, $charizard, 'fr', 2);
        $this->persistOwnedCard($user, $this->persistCard('Pikachu', '025', $set), 'fr');

        // A page of one card: the two languages must come together and count once.
        $this->get($token, '/api/collection?limit=1');

        self::assertResponseIsSuccessful();
        $body = $this->responseBody();
        self::assertSame(2, $body['meta']['total']);
        self::assertCount(1, $body['data']);
        self::assertSame('Charizard', $body['data'][0]['card']['name']);
        self::assertSame(['fr', 'ja'], array_column($body['data'][0]['owned'], 'language'));
        self::assertSame([2, 1], array_column($body['data'][0]['owned'], 'quantity'));
    }

    public function testListNeverMixesInAnotherUsersCopiesOfTheSameCard(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();
        $card = $this->persistCard('Charizard', '004');
        $this->persistOwnedCard($user, $card, 'fr', 1);
        $this->persistOwnedCard($other, $card, 'ja', 9);

        $this->get($this->tokenFor($user), '/api/collection');

        $owned = $this->responseBody()['data'][0]['owned'];
        self::assertSame(['fr'], array_column($owned, 'language'));
        self::assertSame([1], array_column($owned, 'quantity'));
    }

    public function testShowCardReturnsAnEmptyListForACardThatIsNotOwned(): void
    {
        $card = $this->persistCard('Charizard', '004');

        $this->get($this->tokenFor($this->createUser()), '/api/collection/cards/'.$card->getId());

        self::assertResponseIsSuccessful();
        self::assertSame(['data' => []], $this->responseBody());
    }

    public function testDeleteRemovesOnlyTheGivenLanguage(): void
    {
        $user = $this->createUser();
        $card = $this->persistCard('Charizard', '004');
        $this->persistOwnedCard($user, $card, 'fr');
        $this->persistOwnedCard($user, $card, 'ja');
        $token = $this->tokenFor($user);

        $this->delete($token, $card, 'fr');

        self::assertResponseStatusCodeSame(204);
        self::assertNull($this->findOwnedCard($user, $card, 'fr'));
        self::assertNotNull($this->findOwnedCard($user, $card, 'ja'));
    }

    public function testDeleteOfACardThatIsNotOwnedStillSucceeds(): void
    {
        $card = $this->persistCard('Charizard', '004');

        $this->delete($this->tokenFor($this->createUser()), $card, 'fr');

        self::assertResponseStatusCodeSame(204);
    }

    /**
     * The security test of this feature: whatever one user does, another
     * user's collection is neither shown to them nor changed.
     */
    public function testAUserNeverSeesNorChangesAnotherUsersCollection(): void
    {
        $owner = $this->createUser();
        $intruder = $this->createUser();
        $card = $this->persistCard('Charizard', '004');
        $this->persistOwnedCard($owner, $card, 'fr', 5);
        $token = $this->tokenFor($intruder);

        $this->get($token, '/api/collection');
        self::assertSame([], $this->responseBody()['data']);
        self::assertSame(0, $this->responseBody()['meta']['total']);

        $this->get($token, '/api/collection/cards/'.$card->getId());
        self::assertSame([], $this->responseBody()['data']);

        // Same card, same language: this creates the intruder's own entry...
        $this->put($token, $card, 'fr', ['quantity' => 1]);
        self::assertResponseStatusCodeSame(201);
        // ...and deleting it leaves the owner's untouched.
        $this->delete($token, $card, 'fr');
        self::assertResponseStatusCodeSame(204);

        $this->em->clear();
        $ownersEntry = $this->findOwnedCard($owner, $card, 'fr');
        self::assertNotNull($ownersEntry);
        self::assertSame(5, $ownersEntry->getQuantity());
    }

    public function testListReturnsTheCollectionWithFiltersAndPagination(): void
    {
        $user = $this->createUser();
        $token = $this->tokenFor($user);
        $set = $this->persistSet();
        $otherSet = $this->persistSet();
        foreach (['001' => 'Bulbasaur', '002' => 'Ivysaur', '003' => 'Venusaur'] as $number => $name) {
            $this->persistOwnedCard($user, $this->persistCard($name, $number, $set), 'fr');
        }
        $this->persistOwnedCard($user, $this->persistCard('Pikachu', '025', $otherSet), 'fr');
        // In the catalog but not owned: must not appear.
        $this->persistCard('Mewtwo', '150', $set);
        $this->em->flush();

        $this->get($token, '/api/collection?'.http_build_query(['set' => $set->getCode(), 'limit' => 2, 'page' => 2]));

        self::assertResponseIsSuccessful();
        $body = $this->responseBody();
        self::assertSame(['Venusaur'], array_column(array_column($body['data'], 'card'), 'name'));
        self::assertSame(['total' => 3, 'page' => 2, 'limit' => 2, 'totalPages' => 2], $body['meta']);

        $this->get($token, '/api/collection?q=pika');

        self::assertSame(['Pikachu'], array_column(array_column($this->responseBody()['data'], 'card'), 'name'));
    }

    public function testCompletionCountsOwnedCardsAmongThoseMatchingTheSearch(): void
    {
        $user = $this->createUser();
        $token = $this->tokenFor($user);
        $set = $this->persistSet();
        $otherSet = $this->persistSet();
        $fireWyrm = $this->persistCard('Fire Wyrm', '001', $set);
        $iceWyrm = $this->persistCard('Ice Wyrm', '002', $set);
        $this->persistCard('Storm Wyrm', '003', $set);
        $this->persistCard('Fox', '004', $set);
        $elderWyrm = $this->persistCard('Elder Wyrm', '001', $otherSet);
        // Two languages of the same card: one card owned, not two.
        $this->persistOwnedCard($user, $fireWyrm, 'fr', 3);
        $this->persistOwnedCard($user, $fireWyrm, 'ja');
        $this->persistOwnedCard($user, $iceWyrm, 'fr');
        $this->persistOwnedCard($user, $elderWyrm, 'fr');

        $this->get($token, '/api/collection/completion?'.http_build_query(['q' => 'wyrm', 'set' => $set->getCode()]));

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['total' => 3, 'owned' => 2, 'ownedCardIds' => [(string) $fireWyrm->getId(), (string) $iceWyrm->getId()]],
            $this->responseBody(),
        );

        // The other set's card only counts once the set filter is gone.
        $this->get($token, '/api/collection/completion?q=WYRM&limit=100');
        self::assertSame(4, $this->responseBody()['total']);
        self::assertSame(3, $this->responseBody()['owned']);
    }

    public function testCompletionListsOwnedCardsOfTheRequestedPageOnly(): void
    {
        $user = $this->createUser();
        $token = $this->tokenFor($user);
        $set = $this->persistSet();
        $first = $this->persistCard('First', '001', $set);
        $this->persistCard('Second', '002', $set);
        $third = $this->persistCard('Third', '003', $set);
        $this->persistOwnedCard($user, $first, 'fr');
        $this->persistOwnedCard($user, $third, 'fr');

        $this->get($token, '/api/collection/completion?'.http_build_query(['set' => $set->getCode(), 'limit' => 2, 'page' => 2]));

        // The counts cover the whole search, the ids the second page of it.
        self::assertSame(
            ['total' => 3, 'owned' => 2, 'ownedCardIds' => [(string) $third->getId()]],
            $this->responseBody(),
        );
    }

    public function testCompletionIgnoresWhatOtherUsersOwn(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();
        $set = $this->persistSet();
        $this->persistOwnedCard($other, $this->persistCard('Fire Wyrm', '001', $set), 'fr');
        $this->persistCard('Ice Wyrm', '002', $set);

        $this->get($this->tokenFor($user), '/api/collection/completion?set='.$set->getCode());

        self::assertSame(['total' => 2, 'owned' => 0, 'ownedCardIds' => []], $this->responseBody());
    }

    public function testCompletionOfASearchWithoutResultIsEmpty(): void
    {
        $this->get($this->tokenFor($this->createUser()), '/api/collection/completion?set=no-such-set');

        self::assertResponseIsSuccessful();
        self::assertSame(['total' => 0, 'owned' => 0, 'ownedCardIds' => []], $this->responseBody());
    }

    public function testCompletionRejectsInvalidPagination(): void
    {
        $this->get($this->tokenFor($this->createUser()), '/api/collection/completion?limit=0');

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidPayloads')]
    public function testPutRejectsAnInvalidBody(array $payload): void
    {
        $card = $this->persistCard('Charizard', '004');

        $this->put($this->tokenFor($this->createUser()), $card, 'fr', $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('error', $this->responseBody());
        self::assertSame(0, $this->countOwnedCards());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'quantity of zero' => [['quantity' => 0]];
        yield 'negative quantity' => [['quantity' => -1]];
        yield 'quantity that is not a number' => [['quantity' => 'many']];
        yield 'missing quantity' => [['condition' => 'mint']];
        yield 'unknown condition' => [['quantity' => 1, 'condition' => 'brand_new']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidLanguages')]
    public function testPutAndDeleteRejectAnInvalidLanguage(string $language): void
    {
        $card = $this->persistCard('Charizard', '004');
        $token = $this->tokenFor($this->createUser());

        $this->put($token, $card, $language, ['quantity' => 1]);
        self::assertResponseStatusCodeSame(422);

        $this->delete($token, $card, $language);
        self::assertResponseStatusCodeSame(422);

        self::assertSame(0, $this->countOwnedCards());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLanguages(): iterable
    {
        yield 'upper case' => ['FR'];
        yield 'three letters' => ['fra'];
        yield 'digits' => ['f1'];
    }

    public function testRoutesAnswer404ForAnUnknownCard(): void
    {
        $token = $this->tokenFor($this->createUser());

        $this->get($token, '/api/collection/cards/'.self::UNKNOWN_CARD_ID);
        self::assertResponseStatusCodeSame(404);

        $this->client->jsonRequest('PUT', '/api/collection/cards/'.self::UNKNOWN_CARD_ID.'/fr', ['quantity' => 1], $this->authorization($token));
        self::assertResponseStatusCodeSame(404);

        $this->client->request('DELETE', '/api/collection/cards/'.self::UNKNOWN_CARD_ID.'/fr', server: $this->authorization($token));
        self::assertResponseStatusCodeSame(404);
    }

    public function testDeletingAUserDeletesTheirCollection(): void
    {
        $user = $this->createUser();
        $this->persistOwnedCard($user, $this->persistCard('Charizard', '004'), 'fr');
        $this->em->flush();
        self::assertSame(1, $this->countOwnedCards());

        // Straight SQL: the point is that the database itself cascades.
        $this->em->getConnection()->executeStatement('DELETE FROM app_user WHERE id = :id', ['id' => $user->getId()->toRfc4122()]);

        self::assertSame(0, $this->countOwnedCards());
    }

    private function tokenFor(User $user): string
    {
        // Entities created so far must exist in the database before a request reads them.
        $this->em->flush();
        $this->login($user->getEmail());

        return $this->responseBody()['token'];
    }

    /**
     * @return array<string, string>
     */
    private function authorization(string $token): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer '.$token];
    }

    private function get(string $token, string $uri): void
    {
        $this->client->request('GET', $uri, server: $this->authorization($token));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function put(string $token, Card $card, string $language, array $payload): void
    {
        $this->client->jsonRequest('PUT', '/api/collection/cards/'.$card->getId().'/'.$language, $payload, $this->authorization($token));
    }

    private function delete(string $token, Card $card, string $language): void
    {
        $this->client->request('DELETE', '/api/collection/cards/'.$card->getId().'/'.$language, server: $this->authorization($token));
    }

    private function persistSet(): CardSet
    {
        $game = new Game('Game '.uniqid(), 'game-'.uniqid());
        $set = new CardSet($game, 'Set', 'SET-'.uniqid());
        $this->em->persist($game);
        $this->em->persist($set);

        return $set;
    }

    private function persistCard(string $name, string $number, ?CardSet $set = null): Card
    {
        $card = new Card($set ?? $this->persistSet(), $name, $number);
        $this->em->persist($card);
        $this->em->flush();

        return $card;
    }

    private function persistOwnedCard(User $user, Card $card, string $language, int $quantity = 1): OwnedCard
    {
        $ownedCard = new OwnedCard($user, $card, $language, $quantity);
        $this->em->persist($ownedCard);
        $this->em->flush();

        return $ownedCard;
    }

    private function findOwnedCard(User $user, Card $card, string $language): ?OwnedCard
    {
        return $this->em->getRepository(OwnedCard::class)->findOneBy([
            'user' => $user->getId(),
            'card' => $card->getId(),
            'language' => $language,
        ]);
    }

    /** Counts in the database, whatever the entity manager has in memory. */
    private function countOwnedCards(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM owned_card');
    }
}
