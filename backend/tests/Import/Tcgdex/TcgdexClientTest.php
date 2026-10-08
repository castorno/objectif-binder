<?php

declare(strict_types=1);

namespace App\Tests\Import\Tcgdex;

use App\Import\Source\Tcgdex\TcgdexClient;
use App\Import\Source\Tcgdex\TcgdexException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TcgdexClientTest extends TestCase
{
    use TcgdexResponses;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-10-08 12:00:00');
    }

    public function testListsTheSetsOfTheCatalogLanguage(): void
    {
        $http = new MockHttpClient([new MockResponse('[{"id": "ef1", "name": "Premières Braises"}, {"id": "ef2", "name": "Cendres"}]')], 'https://api.example');

        self::assertSame(['ef1', 'ef2'], $this->client($http)->fetchSetIds());
    }

    public function testFetchesASetWithoutItsListOfCards(): void
    {
        $response = $this->setResponse('ef1');
        $http = new MockHttpClient([$response], 'https://api.example');

        $set = $this->client($http)->fetchSet('ef1');

        self::assertSame('https://api.example/v2/fr/sets/ef1', $response->getRequestUrl());
        self::assertSame('Premières Braises', $set['name']);
        self::assertSame('2025-03-01', $set['releaseDate']);
        self::assertArrayNotHasKey('cards', $set);
        // Counted from the cards listed in French, not the worldwide total.
        self::assertSame(3, $set['localCardCount']);
    }

    public function testAsksForTheCardsOfASetInTheCatalogLanguage(): void
    {
        $response = $this->cardsResponse($this->cardsOfSet('ef1'));
        $http = new MockHttpClient([$response], 'https://api.example');

        $cards = $this->client($http)->fetchCards('ef1');

        self::assertCount(3, $cards);
        self::assertSame('POST', $response->getRequestMethod());
        self::assertSame('https://api.example/v2/graphql', $response->getRequestUrl());

        $body = json_decode((string) $response->getRequestOptions()['body'], true);
        self::assertStringContainsString('@locale(lang: "fr")', $body['query']);
        self::assertSame(['id' => 'ef1-'], $body['variables']['filters']);
        self::assertSame(1, $body['variables']['pagination']['page']);
    }

    /**
     * The API filters on a part of the card id: "ef1-" is also inside
     * "xef1-4", a card of another set.
     */
    public function testDropsTheCardsOfOtherSetsThatTheFilterLetThrough(): void
    {
        $cards = [...$this->cardsOfSet('ef1'), ['id' => 'xef1-4', 'localId' => '4', 'name' => 'Intrus', 'set' => ['id' => 'xef1']]];
        $http = new MockHttpClient([$this->cardsResponse($cards)], 'https://api.example');

        self::assertSame(['ef1-1', 'ef1-2', 'ef1-3'], array_column($this->client($http)->fetchCards('ef1'), 'id'));
    }

    public function testKeepsAskingForPagesUntilOneIsNotFull(): void
    {
        $fullPage = array_fill(0, 1000, ['name' => 'Braisewyrm', 'dexId' => [7]]);
        $lastPage = [['name' => 'Givrenard', 'dexId' => [12]]];
        $responses = [$this->cardsResponse($fullPage), $this->cardsResponse($lastPage)];
        $http = new MockHttpClient($responses, 'https://api.example');

        $cards = $this->client($http)->fetchCreatureNames();

        self::assertCount(1001, $cards);
        self::assertSame(2, $http->getRequestsCount());
        self::assertSame(2, json_decode((string) $responses[1]->getRequestOptions()['body'], true)['variables']['pagination']['page']);
    }

    /**
     * A GraphQL API answers 200 even when the query failed: the status code
     * alone would let the error pass for an empty set.
     */
    public function testAnErrorInsideA200AnswerIsAFailure(): void
    {
        $http = new MockHttpClient([new MockResponse('{"errors": [{"message": "Something broke."}], "data": {"cards": null}}')], 'https://api.example');

        $this->expectException(TcgdexException::class);
        $this->expectExceptionMessage('Something broke.');

        $this->client($http)->fetchCards('ef1');
    }

    public function testAnHttpErrorIsAFailure(): void
    {
        $http = new MockHttpClient([new MockResponse('Not found', ['http_code' => 404])], 'https://api.example');

        $this->expectException(TcgdexException::class);

        $this->client($http)->fetchSet('unknown');
    }

    public function testAnAnswerThatIsNotJsonIsAFailure(): void
    {
        $http = new MockHttpClient([new MockResponse('<html>Maintenance</html>')], 'https://api.example');

        $this->expectException(TcgdexException::class);

        $this->client($http)->fetchSetIds();
    }

    /**
     * The clock of the test only moves when the client sleeps: the time
     * that passed is exactly the pauses it took.
     */
    public function testLeavesAPauseBetweenTwoRequests(): void
    {
        $http = new MockHttpClient([$this->setResponse(), $this->setResponse(), $this->setResponse()], 'https://api.example');
        $client = $this->client($http, pauseMilliseconds: 250);
        $start = $this->clock->now();

        $client->fetchSet('ef1');
        self::assertEquals($start, $this->clock->now(), 'Nothing to wait for before the first request.');

        $client->fetchSet('ef1');
        $client->fetchSet('ef1');

        self::assertSame('0.500000', $this->clock->now()->diff($start)->format('%s.%F'));
    }

    private function client(MockHttpClient $http, int $pauseMilliseconds = 0): TcgdexClient
    {
        return new TcgdexClient($http, $this->clock, $pauseMilliseconds);
    }
}
