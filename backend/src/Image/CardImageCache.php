<?php

declare(strict_types=1);

namespace App\Image;

use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Keeps a copy of the pictures of the cards, which someone else serves, and
 * serves them from here: a picture is asked from its source once, the first
 * time someone looks at it, and no longer depends on the source answering.
 *
 * Off unless CARD_IMAGE_CACHE is set: the pictures are artwork owned by the
 * publishers of the games, and an installation that turns this on stores
 * and serves them itself. The repository never holds any.
 *
 * A picture is addressed by where its source serves it, signed: this route
 * only ever fetches addresses the application itself handed out, never one
 * a visitor made up.
 */
final class CardImageCache
{
    /** A copy older than this is checked against its source again. */
    public const string MAX_AGE = '30 days';

    private const int MAX_BYTES = 5 * 1024 * 1024;

    private const array CONTENT_TYPES = ['image/webp', 'image/png', 'image/jpeg', 'image/avif'];

    /** @var list<string> */
    private readonly array $hosts;

    private readonly bool $enabled;

    /**
     * @param string $hosts the hosts pictures may be fetched from, separated by commas
     */
    public function __construct(
        #[Autowire(env: 'bool:CARD_IMAGE_CACHE')]
        bool $enabled,
        #[Autowire(env: 'CARD_IMAGE_CACHE_HOSTS')]
        string $hosts,
        #[Autowire('%kernel.project_dir%/var/card-images')]
        private readonly string $directory,
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
        private readonly HttpClientInterface $httpClient,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        // Addresses are signed with the secret of the application: without
        // one, anybody could sign their own. No secret, no copies.
        $this->enabled = $enabled && '' !== $secret;
        $this->hosts = array_values(array_filter(array_map(trim(...), explode(',', strtolower($hosts)))));
    }

    /**
     * The address to give a browser for a picture: its copy here when
     * copies are kept and its source is one we fetch from, otherwise the
     * address of the source, as it is.
     */
    public function addressOf(?string $sourceUrl): ?string
    {
        if (null === $sourceUrl || !$this->enabled || !$this->isFetchable($sourceUrl)) {
            return $sourceUrl;
        }

        $encoded = self::encode($sourceUrl);

        return sprintf('/api/card-images/%s/%s', $this->sign($encoded), $encoded);
    }

    /**
     * The copy of a picture, fetched from its source if there is none yet.
     *
     * @return StoredImage|null null when the address was not handed out by addressOf(),
     *                              or when the picture cannot be had right now
     */
    public function find(string $signature, string $encoded): ?StoredImage
    {
        if (!$this->enabled || !hash_equals($this->sign($encoded), $signature)) {
            return null;
        }

        $sourceUrl = self::decode($encoded);
        if (null === $sourceUrl || !$this->isFetchable($sourceUrl)) {
            return null;
        }

        $path = $this->pathOf($sourceUrl);
        $meta = $this->readMeta($path);

        if (null !== $meta && $meta['checkedAt'] > $this->clock->now()->modify('-'.self::MAX_AGE)->getTimestamp()) {
            return new StoredImage($path, $meta['contentType'], $meta['etag']);
        }

        return $this->fetch($sourceUrl, $path, $meta);
    }

    /**
     * @param array{contentType: string, etag: ?string, checkedAt: int}|null $meta what is known of the copy already there
     */
    private function fetch(string $sourceUrl, string $path, ?array $meta): ?StoredImage
    {
        // An old copy beats none: it is what a failure falls back on.
        $stale = null === $meta ? null : new StoredImage($path, $meta['contentType'], $meta['etag']);

        try {
            $response = $this->httpClient->request('GET', $sourceUrl, [
                'timeout' => 10,
                'max_duration' => 20,
                'max_redirects' => 0,
                // The source answers "not changed" without sending the picture again.
                'headers' => null === $meta || null === $meta['etag'] ? [] : ['If-None-Match' => $meta['etag']],
            ]);
            $status = $response->getStatusCode();

            if (304 === $status && null !== $meta) {
                $this->writeMeta($path, $meta['contentType'], $meta['etag']);

                return $stale;
            }

            $headers = $response->getHeaders(false);
            $contentType = strtolower(trim(explode(';', $headers['content-type'][0] ?? '')[0]));
            if (200 !== $status || !\in_array($contentType, self::CONTENT_TYPES, true)) {
                $this->logger->warning('Card picture refused: not a picture.', ['url' => $sourceUrl, 'status' => $status, 'contentType' => $contentType]);

                return $stale;
            }
            if ((int) ($headers['content-length'][0] ?? 0) > self::MAX_BYTES) {
                $this->logger->warning('Card picture refused: too large.', ['url' => $sourceUrl]);

                return $stale;
            }

            $content = '';
            foreach ($this->httpClient->stream($response) as $chunk) {
                $content .= $chunk->getContent();
                // Whatever the source announced: stop reading past the limit.
                if (\strlen($content) > self::MAX_BYTES) {
                    $response->cancel();
                    $this->logger->warning('Card picture refused: too large.', ['url' => $sourceUrl]);

                    return $stale;
                }
            }
        } catch (ExceptionInterface $exception) {
            $this->logger->warning('Card picture could not be fetched.', ['url' => $sourceUrl, 'exception' => $exception]);

            return $stale;
        }

        $etag = $headers['etag'][0] ?? null;
        // Written under another name, then renamed: a file with the final
        // name is always a whole picture.
        $this->filesystem->dumpFile($path, $content);
        $this->writeMeta($path, $contentType, $etag);

        return new StoredImage($path, $contentType, $etag);
    }

    /**
     * Only plain https addresses on a host we were told to fetch from: an
     * import can bring in any address, and this server must not be made to
     * call wherever a file says.
     */
    private function isFetchable(string $url): bool
    {
        $parts = parse_url($url);

        return \is_array($parts)
            && 'https' === strtolower($parts['scheme'] ?? '')
            // Neither credentials nor another port: nothing but the plain address of a picture.
            && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port'])
            && \in_array(strtolower($parts['host'] ?? ''), $this->hosts, true);
    }

    private function pathOf(string $sourceUrl): string
    {
        $hash = hash('sha256', $sourceUrl);

        return sprintf('%s/%s/%s/%s', $this->directory, substr($hash, 0, 2), substr($hash, 2, 2), $hash);
    }

    /**
     * @return array{contentType: string, etag: ?string, checkedAt: int}|null
     */
    private function readMeta(string $path): ?array
    {
        if (!is_file($path) || !is_file($path.'.json')) {
            return null;
        }

        $meta = json_decode((string) file_get_contents($path.'.json'), true);
        if (!\is_array($meta) || !\is_string($meta['contentType'] ?? null) || !\is_int($meta['checkedAt'] ?? null)) {
            return null;
        }

        return ['contentType' => $meta['contentType'], 'etag' => \is_string($meta['etag'] ?? null) ? $meta['etag'] : null, 'checkedAt' => $meta['checkedAt']];
    }

    private function writeMeta(string $path, string $contentType, ?string $etag): void
    {
        $this->filesystem->dumpFile($path.'.json', json_encode(
            ['contentType' => $contentType, 'etag' => $etag, 'checkedAt' => $this->clock->now()->getTimestamp()],
            \JSON_THROW_ON_ERROR,
        ));
    }

    private function sign(string $encoded): string
    {
        return substr(hash_hmac('sha256', $encoded, $this->secret), 0, 32);
    }

    private static function encode(string $url): string
    {
        return rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
    }

    private static function decode(string $encoded): ?string
    {
        $url = base64_decode(strtr($encoded, '-_', '+/'), true);

        return false === $url || '' === $url ? null : $url;
    }
}
