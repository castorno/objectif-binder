<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Card;
use App\Entity\CardIdentity;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Image\CardImages;
use App\Image\RemoteImageCache;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The picture kept on the installation for a card: shown wherever the card
 * is, in place of the one its source serves, if any.
 */
final class CustomCardImageControllerTest extends AuthWebTestCase
{
    /** A real picture, one pixel wide. */
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private string $directory;
    private string $file;
    private CardImages $cardImages;
    private Game $game;
    private CardSet $set;
    private Card $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/custom-card-images-'.bin2hex(random_bytes(6));
        $this->file = sys_get_temp_dir().'/card-picture-'.bin2hex(random_bytes(6)).'.png';
        new Filesystem()->dumpFile($this->file, base64_decode(self::PNG));
        // The pictures of these tests go to a directory of their own, removed afterwards.
        $container = static::getContainer();
        $this->cardImages = new CardImages($container->get(RemoteImageCache::class), $this->em, $this->directory);
        $container->set(CardImages::class, $this->cardImages);

        $this->game = new Game('Game '.uniqid(), 'game-'.uniqid());
        $this->set = new CardSet($this->game, 'Main Set', 'MAIN');
        $this->card = new Card($this->set, 'Ember Wyrm', '1');
        $this->em->persist($this->game);
        $this->em->persist($this->set);
        $this->em->persist($this->card);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove([$this->directory, $this->file]);
        parent::tearDown();
    }

    public function testACardWithoutPictureShowsTheOneKeptHereInTheListAsOnItsPage(): void
    {
        $this->cardImages->attach($this->card, $this->file);
        $name = $this->card->getCustomImage();

        $this->client->request('GET', '/api/cards?game='.$this->game->getSlug());
        self::assertSame('/api/custom-card-images/small/'.$name, $this->responseBody()['data'][0]['imageUrl']);
        $this->client->request('GET', '/api/cards/'.$this->card->getId());
        self::assertSame('/api/custom-card-images/small/'.$name, $this->responseBody()['imageUrl']);
        self::assertSame('/api/custom-card-images/large/'.$name, $this->responseBody()['largeImageUrl']);

        // And its set no longer counts as one without pictures.
        $this->client->request('GET', '/api/games/'.$this->game->getSlug().'/sets');
        self::assertTrue($this->responseBody()[0]['hasPictures']);
    }

    /**
     * A source may name a picture it does not serve: what the installation
     * was given is what it shows.
     */
    public function testComesBeforeThePictureOfTheSource(): void
    {
        $identity = new CardIdentity($this->game, 'Wyrm', 'identity-'.uniqid())->setSortOrder(1);
        $this->card->addIdentity($identity)->setImageUrl('https://pictures.example.org/1/low.webp')->setLargeImageUrl('https://pictures.example.org/1/high.webp');
        $this->em->persist($identity);
        $this->em->flush();

        $this->client->request('GET', '/api/cards/'.$this->card->getId());
        self::assertSame('https://pictures.example.org/1/low.webp', $this->responseBody()['imageUrl']);

        $this->cardImages->attach($this->card, $this->file);
        $name = $this->card->getCustomImage();

        $this->client->request('GET', '/api/cards/'.$this->card->getId());
        self::assertSame('/api/custom-card-images/small/'.$name, $this->responseBody()['imageUrl']);
        self::assertSame('/api/custom-card-images/large/'.$name, $this->responseBody()['largeImageUrl']);
        $this->client->request('GET', '/api/identities?game='.$this->game->getSlug());
        self::assertSame('/api/custom-card-images/small/'.$name, $this->responseBody()['data'][0]['imageUrl']);
    }

    public function testStandsForTheIdentityOfTheCard(): void
    {
        $identity = new CardIdentity($this->game, 'Wyrm', 'identity-'.uniqid())->setSortOrder(1);
        $this->card->addIdentity($identity);
        $this->em->persist($identity);
        $this->cardImages->attach($this->card, $this->file);

        $this->client->request('GET', '/api/identities?game='.$this->game->getSlug());

        self::assertSame('/api/custom-card-images/small/'.$this->card->getCustomImage(), $this->responseBody()['data'][0]['imageUrl']);
    }

    public function testServesThePictureToAnyoneAndNothingElse(): void
    {
        $this->cardImages->attach($this->card, $this->file);
        $name = $this->card->getCustomImage();

        // No token: the picture is as public as the catalog.
        $this->client->request('GET', '/api/custom-card-images/large/'.$name);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertInstanceOf(BinaryFileResponse::class, $this->client->getResponse());

        foreach (['/small/'.str_repeat('a', 32).'.png', '/medium/'.$name, '/large/..%2F..%2Fsecrets.png', '/large/'.str_replace('.png', '.svg', (string) $name)] as $path) {
            $this->client->request('GET', '/api/custom-card-images'.$path);
            self::assertResponseStatusCodeSame(404, $path);
        }
    }
}
