<?php

declare(strict_types=1);

namespace App\Tests\Image;

use App\Image\RemoteImageCache;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The copies kept of the card pictures. No test touches the network.
 */
final class RemoteImageCacheTest extends TestCase
{
    private const string PICTURE = 'https://pictures.example.org/fr/ef1/1/low.webp';

    private string $directory;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/card-images-'.bin2hex(random_bytes(6));
        $this->clock = new MockClock('2026-10-10 12:00:00');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->directory);
    }

    public function testLeavesAddressesAloneWhenCopiesAreNotKept(): void
    {
        $cache = $this->cache(new MockHttpClient([]), enabled: false);

        self::assertSame(self::PICTURE, $cache->addressOf(self::PICTURE));
        self::assertNull($cache->addressOf(null));
    }

    /**
     * Addresses are signed with the secret of the application. Without a
     * secret anybody could sign one: copies are then not kept at all.
     */
    public function testKeepsNoCopyWhenTheApplicationHasNoSecret(): void
    {
        $http = new MockHttpClient([]);
        $cache = new RemoteImageCache(true, 'pictures.example.org', $this->directory, '', $http, $this->clock, new NullLogger());

        self::assertSame(self::PICTURE, $cache->addressOf(self::PICTURE));
        // Even an address signed with the empty secret is refused.
        $encoded = rtrim(strtr(base64_encode(self::PICTURE), '+/', '-_'), '=');
        self::assertNull($cache->find(substr(hash_hmac('sha256', $encoded, ''), 0, 32), $encoded));
        self::assertSame(0, $http->getRequestsCount());
    }

    public function testFetchesAPictureOnceThenServesItsCopy(): void
    {
        $http = new MockHttpClient([$this->picture('WEBP-BYTES', '"v1"')]);
        $cache = $this->cache($http);

        [$signature, $encoded] = $this->addressParts($cache);
        $first = $cache->find($signature, $encoded);
        $second = $cache->find($signature, $encoded);

        self::assertNotNull($first);
        self::assertSame('WEBP-BYTES', file_get_contents($first->path));
        self::assertSame('image/webp', $first->contentType);
        self::assertSame('"v1"', $first->etag);
        self::assertEquals($first, $second);
        // The source was asked once: the second look costs it nothing.
        self::assertSame(1, $http->getRequestsCount());
        // Kept under the cache directory, wherever the address pointed.
        self::assertStringStartsWith($this->directory.'/', $first->path);
    }

    /**
     * An address is only served if this application handed it out: nobody
     * can make the server fetch a picture of their choosing.
     */
    public function testRefusesAnAddressItDidNotSign(): void
    {
        $http = new MockHttpClient([]);
        $cache = $this->cache($http);
        [$signature, $encoded] = $this->addressParts($cache);
        $other = rtrim(strtr(base64_encode('https://pictures.example.org/other.webp'), '+/', '-_'), '=');

        self::assertNull($cache->find($signature, $other));
        self::assertNull($cache->find(str_repeat('0', 32), $encoded));
        self::assertNull($cache->find($signature, '!!!'));
        self::assertSame(0, $http->getRequestsCount());
    }

    /**
     * An import can bring in any address: only those on a host we were told
     * to fetch from get a copy, the others are left to the browser.
     */
    #[DataProvider('addressesNotFetched')]
    public function testOnlyKeepsCopiesOfPicturesFromTheHostsItWasGiven(string $address): void
    {
        self::assertSame($address, $this->cache(new MockHttpClient([]))->addressOf($address));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function addressesNotFetched(): iterable
    {
        yield 'another host' => ['https://elsewhere.example.org/1.webp'];
        yield 'a host that only ends like ours' => ['https://evil-pictures.example.org/1.webp'];
        yield 'our host as a sub-domain of another' => ['https://pictures.example.org.evil.test/1.webp'];
        yield 'plain http' => ['http://pictures.example.org/1.webp'];
        yield 'another port' => ['https://pictures.example.org:8443/1.webp'];
        yield 'credentials in the address' => ['https://user:secret@pictures.example.org/1.webp'];
        yield 'the machine itself' => ['https://localhost/1.webp'];
    }

    /**
     * @param \Closure(): MockResponse $answer
     */
    #[DataProvider('answersThatAreNoPicture')]
    public function testKeepsNothingOfAnAnswerThatIsNotAPicture(\Closure $answer): void
    {
        $cache = $this->cache(new MockHttpClient([$answer()]));

        self::assertNull($cache->find(...$this->addressParts($cache)));
        self::assertDirectoryDoesNotExist($this->directory);
    }

    /**
     * @return iterable<string, array{\Closure(): MockResponse}>
     */
    public static function answersThatAreNoPicture(): iterable
    {
        yield 'a page' => [static fn (): MockResponse => new MockResponse('<html>', ['response_headers' => ['content-type' => 'text/html']])];
        yield 'a script dressed as a vector picture' => [static fn (): MockResponse => new MockResponse('<svg onload="x()">', ['response_headers' => ['content-type' => 'image/svg+xml']])];
        yield 'not found' => [static fn (): MockResponse => new MockResponse('', ['http_code' => 404, 'response_headers' => ['content-type' => 'image/webp']])];
        yield 'a redirect elsewhere' => [static fn (): MockResponse => new MockResponse('', ['http_code' => 302, 'response_headers' => ['location' => 'https://elsewhere.example.org/']])];
        yield 'a network failure' => [static fn (): MockResponse => new MockResponse('', ['error' => 'Connection refused'])];
        yield 'too large' => [static fn (): MockResponse => new MockResponse(str_repeat('x', 5 * 1024 * 1024 + 1), ['response_headers' => ['content-type' => 'image/webp']])];
    }

    /**
     * "As long as it does not change at the source": an old copy is checked
     * with the fingerprint the source gave, and only fetched again if the
     * picture is another one.
     */
    public function testChecksAnOldCopyAgainstItsSourceWithoutFetchingItAgain(): void
    {
        $requests = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = $options['normalized_headers']['if-none-match'][0] ?? null;

            return match (\count($requests)) {
                1 => $this->picture('FIRST', '"v1"'),
                2 => new MockResponse('', ['http_code' => 304]),
                default => $this->picture('SECOND', '"v2"'),
            };
        });
        $cache = $this->cache($http);
        $parts = $this->addressParts($cache);

        $cache->find(...$parts);
        $this->clock->modify('+29 days');
        $cache->find(...$parts);
        self::assertCount(1, $requests);

        // Past thirty days: asked again, with the fingerprint. Not changed.
        $this->clock->modify('+2 days');
        $unchanged = $cache->find(...$parts);
        self::assertSame([null, 'If-None-Match: "v1"'], $requests);
        self::assertSame('FIRST', file_get_contents((string) $unchanged?->path));

        // Good for another thirty days.
        $this->clock->modify('+29 days');
        $cache->find(...$parts);
        self::assertCount(2, $requests);

        // Then the picture changed at the source.
        $this->clock->modify('+2 days');
        $changed = $cache->find(...$parts);
        self::assertSame('SECOND', file_get_contents((string) $changed?->path));
        self::assertSame('"v2"', $changed?->etag);
    }

    public function testServesTheOldCopyWhenTheSourceCannotBeAsked(): void
    {
        $http = new MockHttpClient([$this->picture('FIRST', '"v1"'), new MockResponse('', ['error' => 'Connection refused']), new MockResponse('', ['http_code' => 500])]);
        $cache = $this->cache($http);
        $parts = $this->addressParts($cache);
        $cache->find(...$parts);

        $this->clock->modify('+40 days');

        self::assertSame('FIRST', file_get_contents((string) $cache->find(...$parts)?->path));
        self::assertSame('FIRST', file_get_contents((string) $cache->find(...$parts)?->path));
    }

    private function picture(string $content, string $etag): MockResponse
    {
        return new MockResponse($content, ['response_headers' => ['content-type' => 'image/webp', 'etag' => $etag]]);
    }

    /**
     * @return array{string, string} the signature and the encoded address, as the route receives them
     */
    private function addressParts(RemoteImageCache $cache): array
    {
        $address = (string) $cache->addressOf(self::PICTURE);
        self::assertMatchesRegularExpression('#^/api/card-images/[0-9a-f]{32}/[A-Za-z0-9_-]+$#', $address);
        [, , , $signature, $encoded] = explode('/', $address);

        return [$signature, $encoded];
    }

    private function cache(MockHttpClient $http, bool $enabled = true): RemoteImageCache
    {
        return new RemoteImageCache($enabled, 'pictures.example.org', $this->directory, 'not-a-real-secret', $http, $this->clock, new NullLogger());
    }
}
