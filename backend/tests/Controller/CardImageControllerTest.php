<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Card;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Image\RemoteImageCache;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The picture of a card, served from the copy kept here when copies are kept.
 */
final class CardImageControllerTest extends AuthWebTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/card-images-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
        parent::tearDown();
    }

    /**
     * The default: the catalog points to whoever hosts the pictures, and
     * this server holds none.
     */
    public function testACardPointsToTheSourceOfItsPictureWhenCopiesAreNotKept(): void
    {
        $card = $this->persistCard();

        $this->client->request('GET', '/api/cards/'.$card->getId());

        self::assertSame('https://pictures.example.org/1/low.webp', $this->responseBody()['imageUrl']);
        self::assertSame('https://pictures.example.org/1/high.webp', $this->responseBody()['largeImageUrl']);
        // And the route serves nothing, whatever it is asked.
        $this->client->request('GET', '/api/card-images/'.str_repeat('a', 32).'/aHR0cHM6Ly9waWN0dXJlcy5leGFtcGxlLm9yZy8xL2xvdy53ZWJw');
        self::assertResponseStatusCodeSame(404);
    }

    public function testServesThePictureOfACardFromItsCopyToAnyone(): void
    {
        $http = $this->keepCopies([new MockResponse('WEBP-BYTES', ['response_headers' => ['content-type' => 'image/webp', 'etag' => '"v1"']])]);
        $card = $this->persistCard();

        // The catalog now gives an address of this server, in the list as on the card.
        $this->client->request('GET', '/api/cards?game='.$card->getCardSet()->getGame()->getSlug());
        $address = $this->responseBody()['data'][0]['imageUrl'];
        self::assertStringStartsWith('/api/card-images/', $address);
        $this->client->request('GET', '/api/cards/'.$card->getId());
        self::assertSame($address, $this->responseBody()['imageUrl']);
        self::assertStringStartsWith('/api/card-images/', $this->responseBody()['largeImageUrl']);

        // No token: the picture is as public as the catalog.
        $this->client->request('GET', $address);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/webp');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertStringContainsString('immutable', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $response = $this->client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame('WEBP-BYTES', $response->getFile()->getContent());

        // A browser that already has it is told so, and the source is left alone.
        $this->client->request('GET', $address, server: ['HTTP_IF_NONE_MATCH' => '"v1"']);
        self::assertResponseStatusCodeSame(304);
        self::assertSame(1, $http->getRequestsCount());
    }

    public function testAnswers404ForAPictureThatCannotBeHadSoThatThePageShowsItsStandIn(): void
    {
        $this->keepCopies([new MockResponse('', ['error' => 'Connection refused'])]);
        $card = $this->persistCard();
        $this->client->request('GET', '/api/cards/'.$card->getId());
        $address = $this->responseBody()['imageUrl'];

        $this->client->request('GET', $address);
        self::assertResponseStatusCodeSame(404);

        // An address nobody handed out.
        $this->client->request('GET', substr($address, 0, -2).'zz');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @param list<MockResponse> $answers what the source of the pictures answers, in order
     */
    private function keepCopies(array $answers): MockHttpClient
    {
        $http = new MockHttpClient($answers);
        static::getContainer()->set(RemoteImageCache::class, new RemoteImageCache(
            true,
            'pictures.example.org',
            $this->directory,
            'not-a-real-secret',
            $http,
            new MockClock(),
            new NullLogger(),
        ));

        return $http;
    }

    private function persistCard(): Card
    {
        $game = new Game('Game '.uniqid(), 'game-'.uniqid());
        $set = new CardSet($game, 'Set', 'SET-'.uniqid());
        $card = new Card($set, 'Ember Wyrm', '001')
            ->setImageUrl('https://pictures.example.org/1/low.webp')
            ->setLargeImageUrl('https://pictures.example.org/1/high.webp');
        $this->em->persist($game);
        $this->em->persist($set);
        $this->em->persist($card);
        $this->em->flush();

        return $card;
    }
}
