<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\CardSet;
use App\Entity\Game;
use App\Entity\User;
use App\Image\CardImageCache;
use App\Image\SetImages;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The picture an administrator sends for a set. A file sent from a browser
 * is the least trustworthy thing the application receives: most of these
 * tests are about what it refuses.
 */
final class SetImageControllerTest extends AuthWebTestCase
{
    /** A real picture, one pixel wide. */
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private string $directory;
    private string $uploads;
    private CardSet $set;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/set-images-'.bin2hex(random_bytes(6));
        $this->uploads = sys_get_temp_dir().'/set-uploads-'.bin2hex(random_bytes(6));
        new Filesystem()->mkdir($this->uploads);
        // The pictures of these tests go to a directory of their own, removed afterwards.
        $container = static::getContainer();
        $container->set(SetImages::class, new SetImages($container->get(CardImageCache::class), $this->em, $this->directory));

        $game = new Game('Game '.uniqid(), 'game-'.uniqid());
        $this->set = new CardSet($game, 'Main Set', 'MAIN');
        $this->em->persist($game);
        $this->em->persist($this->set);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove([$this->directory, $this->uploads]);
        parent::tearDown();
    }

    public function testOnlyAnAdministratorSendsOrRemovesThePictureOfASet(): void
    {
        $uri = '/api/admin/sets/'.$this->set->getId().'/image';

        $this->client->request('POST', $uri, files: ['image' => $this->file('logo.png', base64_decode(self::PNG))]);
        self::assertResponseStatusCodeSame(401);

        $user = $this->authorization($this->createUser());
        $this->client->request('POST', $uri, files: ['image' => $this->file('logo.png', base64_decode(self::PNG))], server: $user);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('DELETE', $uri, server: $user);
        self::assertResponseStatusCodeSame(403);

        self::assertDirectoryDoesNotExist($this->directory);
    }

    public function testKeepsThePictureUnderANameOfItsOwnAndServesItToAnyone(): void
    {
        $this->upload($this->file('../../evil name.PNG', base64_decode(self::PNG)));

        self::assertResponseIsSuccessful();
        $address = $this->responseBody()['imageUrl'];
        // Nothing of the name the file came with.
        self::assertMatchesRegularExpression('#^/api/set-images/[0-9a-f]{32}\.png$#', $address);
        self::assertCount(1, $this->storedFiles());

        // No token: as public as the catalog.
        $this->client->request('GET', $address);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        $response = $this->client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame(base64_decode(self::PNG), $response->getFile()->getContent());
    }

    /**
     * The picture sent stands in for the imported logo; taking it away
     * brings the logo back, and leaves no file behind.
     */
    public function testThePictureSentReplacesTheLogoUntilItIsRemoved(): void
    {
        $this->set->setLogoUrl('https://pictures.example.org/main/logo.webp');
        $this->em->flush();

        $this->upload($this->file('first.png', base64_decode(self::PNG)));
        $first = $this->responseBody()['imageUrl'];
        $this->upload($this->file('second.png', base64_decode(self::PNG)));
        $second = $this->responseBody()['imageUrl'];

        self::assertNotSame($first, $second);
        // The first one is gone from the disk, and no longer served.
        self::assertCount(1, $this->storedFiles());
        $this->client->request('GET', $first);
        self::assertResponseStatusCodeSame(404);

        // Shown with the rates of the set.
        $admin = $this->authorization($this->createAdmin());
        $this->client->request('GET', '/api/admin/sets/'.$this->set->getId().'/pull-rates', server: $admin);
        self::assertSame($second, $this->responseBody()['imageUrl']);

        $this->client->request('DELETE', '/api/admin/sets/'.$this->set->getId().'/image', server: $admin);
        self::assertResponseIsSuccessful();
        self::assertSame(['imageUrl' => 'https://pictures.example.org/main/logo.webp'], $this->responseBody());
        self::assertCount(0, $this->storedFiles());
    }

    /**
     * @param \Closure(): string $content
     */
    #[DataProvider('filesThatAreNotPictures')]
    public function testKeepsNothingOfAFileThatIsNotAPictureItAccepts(string $name, \Closure $content): void
    {
        $this->upload($this->file($name, $content()));

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->storedFiles());
        $this->em->clear();
        self::assertNull($this->em->getRepository(CardSet::class)->find($this->set->getId())?->getCustomImage());
    }

    /**
     * @return iterable<string, array{string, \Closure(): string}>
     */
    public static function filesThatAreNotPictures(): iterable
    {
        yield 'a script named like a picture' => ['logo.png', static fn (): string => '<?php echo "hello";'];
        yield 'a page named like a picture' => ['logo.jpg', static fn (): string => '<html><script>alert(1)</script></html>'];
        yield 'a vector picture, which can carry script' => ['logo.svg', static fn (): string => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'];
        yield 'a picture cut short' => ['logo.png', static fn (): string => substr(base64_decode(self::PNG), 0, 20)];
        yield 'a picture too large' => ['logo.png', static fn (): string => base64_decode(self::PNG).str_repeat("\0", SetImages::MAX_BYTES)];
        yield 'an empty file' => ['logo.png', static fn (): string => ''];
    }

    public function testAsksForTheFileWhenNoneIsSent(): void
    {
        $this->client->request('POST', '/api/admin/sets/'.$this->set->getId().'/image', server: $this->authorization($this->createAdmin()));

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * Only names made by the application are ever looked for on the disk.
     */
    #[DataProvider('namesThatAreNotOurs')]
    public function testServesNothingUnderANameItDidNotMake(string $name): void
    {
        new Filesystem()->dumpFile($this->directory.'/notes.txt', 'private');

        $this->client->request('GET', '/api/set-images/'.$name);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function namesThatAreNotOurs(): iterable
    {
        yield 'another file of the directory' => ['notes.txt'];
        yield 'a way out of the directory' => ['..%2F..%2F.env'];
        yield 'a picture that does not exist' => [str_repeat('a', 32).'.png'];
        yield 'another extension' => [str_repeat('a', 32).'.php'];
    }

    private function upload(UploadedFile $file): void
    {
        $this->client->request('POST', '/api/admin/sets/'.$this->set->getId().'/image', files: ['image' => $file], server: $this->authorization($this->createAdmin()));
    }

    private function file(string $name, string $content): UploadedFile
    {
        $path = $this->uploads.'/'.bin2hex(random_bytes(6));
        file_put_contents($path, $content);

        // Sent as a picture whatever it holds: the type a browser gives is not to be believed.
        return new UploadedFile($path, $name, 'image/png', test: true);
    }

    /**
     * @return list<string>
     */
    private function storedFiles(): array
    {
        return is_dir($this->directory) ? array_values(array_diff((array) scandir($this->directory), ['.', '..'])) : [];
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
}
