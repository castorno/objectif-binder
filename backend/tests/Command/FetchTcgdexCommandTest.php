<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Import\Source\Tcgdex\TcgdexCardMapper;
use App\Import\Source\Tcgdex\TcgdexClient;
use App\Import\Source\Tcgdex\TcgdexFetcher;
use App\Tests\Import\Tcgdex\TcgdexResponses;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FetchTcgdexCommandTest extends KernelTestCase
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

        parent::tearDown();
    }

    public function testDownloadsTheSetsItIsGiven(): void
    {
        $tester = $this->tester(new MockHttpClient([
            $this->setResponse('ef1', 3), $this->cardsResponse($this->creatureNames()), $this->cardsResponse($this->cardsOfSet('ef1')),
            $this->setResponse('ef2', 3), $this->cardsResponse($this->cardsOfSet('ef2')),
        ]));

        $tester->execute(['--set' => ['ef1', 'ef2']]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('ef1: 3 cards.', $tester->getDisplay());
        self::assertFileExists($this->directory.'/ef1.jsonl');
        self::assertFileExists($this->directory.'/ef2.jsonl');
    }

    public function testKeepsThePictureAddressesOnlyWhenAskedTo(): void
    {
        $answers = fn (): array => [$this->setResponse('ef1', 3), $this->cardsResponse($this->creatureNames()), $this->cardsResponse($this->cardsOfSet('ef1'))];

        $this->tester(new MockHttpClient($answers()))->execute(['--set' => ['ef1']]);
        self::assertStringNotContainsString('imageUrl', (string) file_get_contents($this->directory.'/ef1.jsonl'));

        $this->tester(new MockHttpClient([...$answers(), $this->fallbackPicturesResponse('ef1')]))->execute(['--set' => ['ef1'], '--refresh' => true, '--with-images' => true]);
        self::assertStringContainsString('"imageUrl":"https://assets.example.org/fr/ef1/1/low.webp"', (string) file_get_contents($this->directory.'/ef1.jsonl'));
    }

    public function testAllDownloadsEverySetAndSkipsThoseAlreadyThere(): void
    {
        new Filesystem()->dumpFile($this->directory.'/ef1.jsonl', "{}\n");
        $http = new MockHttpClient([
            new MockResponse('[{"id": "ef1"}, {"id": "ef2"}]'),
            $this->setResponse('ef2', 3), $this->cardsResponse($this->creatureNames()), $this->cardsResponse($this->cardsOfSet('ef2')),
        ]);
        $tester = $this->tester($http);

        $tester->execute(['--all' => true]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('ef1: already downloaded, skipped.', $tester->getDisplay());
        self::assertStringContainsString('ef2: 3 cards.', $tester->getDisplay());
        self::assertSame(4, $http->getRequestsCount());
    }

    public function testStopsAtTheFirstFailureAndKeepsWhatIsDownloaded(): void
    {
        $http = new MockHttpClient([
            $this->setResponse('ef1', 3), $this->cardsResponse($this->creatureNames()), $this->cardsResponse($this->cardsOfSet('ef1')),
            new MockResponse('Server error', ['http_code' => 500]),
        ]);
        $tester = $this->tester($http);

        $tester->execute(['--set' => ['ef1', 'ef2', 'ef3']]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertFileExists($this->directory.'/ef1.jsonl');
        // ef3 was never asked for.
        self::assertSame(4, $http->getRequestsCount());
    }

    public function testWarnsAboutASetThatIsNotWhole(): void
    {
        $tester = $this->tester(new MockHttpClient([$this->setResponse('ef1', 5), $this->cardsResponse($this->creatureNames()), $this->cardsResponse($this->cardsOfSet('ef1'))]));

        $tester->execute(['--set' => ['ef1']]);

        self::assertStringContainsString('3 cards downloaded, where TCGdex lists 5', (string) preg_replace('/\s+/', ' ', $tester->getDisplay()));
    }

    public function testSaysWhichSetsItLeftOut(): void
    {
        $tester = $this->tester(new MockHttpClient([$this->setResponse('ef1', 0), $this->setResponse('mobile1', 3, serie: 'tcgp')]));

        $tester->execute(['--set' => ['ef1', 'mobile1']]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('ef1: no card in French, nothing to download.', $tester->getDisplay());
        self::assertStringContainsString('mobile1: cards of the mobile game, not downloaded.', $tester->getDisplay());
    }

    public function testNeedsEitherSetsOrAllButNotBoth(): void
    {
        $http = new MockHttpClient([]);

        $nothing = $this->tester($http);
        $nothing->execute([]);
        self::assertSame(Command::INVALID, $nothing->getStatusCode());

        $both = $this->tester($http);
        $both->execute(['--set' => ['ef1'], '--all' => true]);
        self::assertSame(Command::INVALID, $both->getStatusCode());

        self::assertSame(0, $http->getRequestsCount());
    }

    /**
     * The command as the application wires it, on a fetcher that talks to
     * canned answers and writes to a temporary directory.
     */
    private function tester(MockHttpClient $http): CommandTester
    {
        self::ensureKernelShutdown();
        $kernel = self::bootKernel();

        static::getContainer()->set(TcgdexFetcher::class, new TcgdexFetcher(
            new TcgdexClient($http, new MockClock(), pauseMilliseconds: 0),
            new TcgdexCardMapper(),
            new Filesystem(),
            $this->directory,
            new NullLogger(),
        ));

        return new CommandTester(new Application($kernel)->find('app:import:fetch-tcgdex'));
    }
}
