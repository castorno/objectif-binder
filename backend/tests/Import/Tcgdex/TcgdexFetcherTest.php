<?php

declare(strict_types=1);

namespace App\Tests\Import\Tcgdex;

use App\Import\Source\Tcgdex\TcgdexCardMapper;
use App\Import\Source\Tcgdex\TcgdexClient;
use App\Import\Source\Tcgdex\TcgdexException;
use App\Import\Source\Tcgdex\TcgdexFetcher;
use App\Import\Source\Tcgdex\TcgdexFetchStatus;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TcgdexFetcherTest extends TestCase
{
    use TcgdexResponses;

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/objectif-binder-tcgdex-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testWritesASetAsAFileOfImportRecords(): void
    {
        $http = new MockHttpClient([$this->setResponse('ef1', 3), $this->cardsResponse($this->creatureNames()), $this->cardsResponse($this->cardsOfSet('ef1'))]);

        $result = $this->fetcher($http)->fetchSet('ef1');

        self::assertSame(TcgdexFetchStatus::Downloaded, $result->status);
        self::assertTrue($result->isComplete());
        self::assertSame(3, $result->cardCount);
        self::assertSame($this->directory.'/ef1.jsonl', $result->path);

        $lines = file($result->path, \FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        self::assertCount(3, $lines);

        $first = json_decode($lines[0], true);
        self::assertSame('Braisewyrm V', $first['name']);
        self::assertSame('Premières Braises', $first['set']['name']);
        self::assertSame('Braisewyrm', $first['identities'][0]['name']);
        // Readable in a text editor: accents are written as they are.
        self::assertStringContainsString('Premières', $lines[0]);
    }

    /**
     * What makes a download resumable: a set already on disk costs no
     * request at all.
     */
    public function testASetAlreadyDownloadedIsNotAskedForAgain(): void
    {
        $http = new MockHttpClient([$this->setResponse('ef1', 3), $this->cardsResponse($this->creatureNames()), $this->cardsResponse($this->cardsOfSet('ef1'))]);
        $fetcher = $this->fetcher($http);
        $fetcher->fetchSet('ef1');

        $result = $fetcher->fetchSet('ef1');

        self::assertSame(TcgdexFetchStatus::AlreadyDownloaded, $result->status);
        self::assertSame(3, $http->getRequestsCount());
    }

    public function testRefreshDownloadsAgain(): void
    {
        $http = new MockHttpClient([
            $this->setResponse('ef1', 3), $this->cardsResponse($this->creatureNames()), $this->cardsResponse($this->cardsOfSet('ef1')),
            $this->setResponse('ef1', 3), $this->cardsResponse($this->creatureNames()), $this->cardsResponse($this->cardsOfSet('ef1')),
        ]);

        $this->fetcher($http)->fetchSet('ef1');
        $result = $this->fetcher($http)->fetchSet('ef1', refresh: true);

        self::assertSame(TcgdexFetchStatus::Downloaded, $result->status);
        self::assertSame(6, $http->getRequestsCount());
    }

    /**
     * The species names cost a listing of every creature card: fetched for
     * the first set, then read from disk, even by a later run.
     */
    public function testTheSpeciesNamesAreFetchedOnceForAllSets(): void
    {
        $http = new MockHttpClient([
            $this->setResponse('ef1', 3), $this->cardsResponse($this->creatureNames()), $this->cardsResponse($this->cardsOfSet('ef1')),
            $this->setResponse('ef2', 3), $this->cardsResponse($this->cardsOfSet('ef2')),
            $this->setResponse('ef3', 3), $this->cardsResponse($this->cardsOfSet('ef3')),
        ]);

        $fetcher = $this->fetcher($http);
        $fetcher->fetchSet('ef1');
        $fetcher->fetchSet('ef2');
        // Another run of the command: a new fetcher, the same directory.
        $this->fetcher($http)->fetchSet('ef3');

        self::assertSame(7, $http->getRequestsCount());
        self::assertFileExists($this->directory.'/_species.json');
    }

    public function testSaysWhenASetIsNotWhole(): void
    {
        $http = new MockHttpClient([$this->setResponse('ef1', 5), $this->cardsResponse($this->creatureNames()), $this->cardsResponse($this->cardsOfSet('ef1'))]);

        $result = $this->fetcher($http)->fetchSet('ef1');

        self::assertFalse($result->isComplete());
        self::assertSame(3, $result->cardCount);
        self::assertSame(5, $result->expectedCardCount);
    }

    public function testAFailureLeavesNoFileBehind(): void
    {
        $http = new MockHttpClient([$this->setResponse('ef1', 3), $this->cardsResponse($this->creatureNames()), new MockResponse('Server error', ['http_code' => 500])]);

        try {
            $this->fetcher($http)->fetchSet('ef1');
            self::fail('The download should have failed.');
        } catch (TcgdexException) {
            self::assertFileDoesNotExist($this->directory.'/ef1.jsonl');
        }
    }

    /**
     * Many sets were never released in French. That is not a failure, and
     * the one request that says so is the only one spent on them.
     */
    public function testASetWithoutCardInTheCatalogLanguageIsSkipped(): void
    {
        $http = new MockHttpClient([$this->setResponse('ef1', 0)]);

        $result = $this->fetcher($http)->fetchSet('ef1');

        self::assertSame(TcgdexFetchStatus::Empty, $result->status);
        self::assertSame(1, $http->getRequestsCount());
        self::assertFileDoesNotExist($this->directory.'/ef1.jsonl');
    }

    /**
     * The cards of the mobile game are not cards one can own: the set is
     * left out, for the price of the one request that says what it is.
     */
    public function testASetOfTheMobileGameIsNotDownloaded(): void
    {
        $http = new MockHttpClient([$this->setResponse('mobile1', 3, serie: 'tcgp')]);

        $result = $this->fetcher($http)->fetchSet('mobile1');

        self::assertSame(TcgdexFetchStatus::Digital, $result->status);
        self::assertSame(1, $http->getRequestsCount());
        self::assertFileDoesNotExist($this->directory.'/mobile1.jsonl');
    }

    /**
     * A card without picture in French gets the English one when there is
     * one. A card with a French picture keeps it.
     */
    public function testFallsBackToTheEnglishPictureOfACardThatHasNoneInFrench(): void
    {
        $fallback = $this->fallbackPicturesResponse('ef1');
        $http = new MockHttpClient([$this->setResponse('ef1', 3), $this->cardsResponse($this->creatureNames()), $this->cardsResponse($this->cardsOfSet('ef1')), $fallback]);

        $result = $this->fetcher($http)->fetchSet('ef1', withImages: true);

        $records = array_map(static fn (string $line): array => json_decode($line, true), (array) file($result->path, \FILE_IGNORE_NEW_LINES));
        self::assertSame('https://assets.example.org/fr/ef1/1/low.webp', $records[0]['imageUrl']);
        self::assertSame('https://assets.example.org/en/ef1/2/low.webp', $records[1]['imageUrl']);
        self::assertSame('https://assets.example.org/en/ef1/2/high.webp', $records[1]['largeImageUrl']);
        // No picture in either language.
        self::assertArrayNotHasKey('imageUrl', $records[2]);

        self::assertStringContainsString('@locale(lang: "en")', json_decode((string) $fallback->getRequestOptions()['body'], true)['query']);
    }

    /**
     * The extra request is only worth it when something is missing, and
     * when pictures were asked for at all.
     */
    public function testDoesNotAskForEnglishPicturesWhenNoneIsNeeded(): void
    {
        $complete = array_map(
            static fn (array $card): array => ['image' => 'https://assets.example.org/fr/'.$card['id']] + $card,
            $this->cardsOfSet('ef1'),
        );
        $http = new MockHttpClient([
            $this->setResponse('ef1', 3), $this->cardsResponse($this->creatureNames()), $this->cardsResponse($complete),
            $this->setResponse('ef2', 3), $this->cardsResponse($this->cardsOfSet('ef2')),
        ]);
        $fetcher = $this->fetcher($http);

        $fetcher->fetchSet('ef1', withImages: true);
        // Cards without picture, but pictures were not asked for.
        $fetcher->fetchSet('ef2');

        self::assertSame(5, $http->getRequestsCount());
    }

    public function testASetThatListsCardsButReturnsNoneIsAFailure(): void
    {
        $http = new MockHttpClient([$this->setResponse('ef1', 3), $this->cardsResponse($this->creatureNames()), $this->cardsResponse([])]);

        $this->expectException(TcgdexException::class);

        $this->fetcher($http)->fetchSet('ef1');
    }

    /**
     * The id becomes a file name: one that walks out of the directory must
     * never get that far.
     */
    public function testRefusesASetIdThatIsNotOne(): void
    {
        $http = new MockHttpClient([]);

        try {
            $this->fetcher($http)->fetchSet('../../etc/passwd');
            self::fail('The id should have been refused.');
        } catch (TcgdexException) {
            self::assertSame(0, $http->getRequestsCount());
        }
    }

    private function fetcher(MockHttpClient $http): TcgdexFetcher
    {
        return new TcgdexFetcher(
            new TcgdexClient($http, new MockClock(), pauseMilliseconds: 0),
            new TcgdexCardMapper(),
            new Filesystem(),
            $this->directory,
            new NullLogger(),
        );
    }
}
