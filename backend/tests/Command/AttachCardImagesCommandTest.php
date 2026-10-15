<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\Card;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Image\CardImages;
use App\Image\RemoteImageCache;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Pictures given to cards from files of the installation. The files come
 * from whoever runs the command, yet only real pictures are kept, under
 * names made here.
 */
final class AttachCardImagesCommandTest extends KernelTestCase
{
    /** A real picture, one pixel wide. */
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private EntityManagerInterface $em;
    private CommandTester $tester;
    private string $kept;
    private string $source;
    private Card $card;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->kept = sys_get_temp_dir().'/custom-card-images-'.bin2hex(random_bytes(6));
        $this->source = sys_get_temp_dir().'/card-pictures-'.bin2hex(random_bytes(6));
        new Filesystem()->mkdir($this->source.'/pictures');
        // The pictures of these tests go to a directory of their own, removed afterwards.
        $container->set(CardImages::class, new CardImages($container->get(RemoteImageCache::class), $this->em, $this->kept));
        $this->tester = new CommandTester(new Application($kernel)->find('app:cards:attach-images'));
        $this->em->getConnection()->beginTransaction();

        $game = new Game('Game '.uniqid(), 'game-'.uniqid());
        $set = new CardSet($game, 'Main Set', 'MAIN');
        $this->card = new Card($set, 'Ember Wyrm', '1')->setExternalId('main-'.uniqid());
        $this->em->persist($game);
        $this->em->persist($set);
        $this->em->persist($this->card);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        $this->em->close();
        new Filesystem()->remove([$this->kept, $this->source]);

        parent::tearDown();
    }

    public function testKeepsBothSizesUnderANameOfItsOwnAndChangesNothingTheSecondTime(): void
    {
        $this->write('../odd name.JPG', base64_decode(self::PNG));
        $this->write('small.png', base64_decode(self::PNG).'smaller');
        $file = $this->csv([[$this->card->getExternalId(), '../odd name.JPG', 'small.png']]);

        self::assertSame(Command::SUCCESS, $this->tester->execute(['file' => $file]));

        $name = $this->reloadedImage();
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}\.png$/', (string) $name);
        self::assertStringEqualsFile($this->kept.'/large/'.$name, base64_decode(self::PNG));
        self::assertStringEqualsFile($this->kept.'/small/'.$name, base64_decode(self::PNG).'smaller');

        $this->tester->execute(['file' => $file]);
        self::assertMatchesRegularExpression('/Unchanged\s+1/', $this->tester->getDisplay());
        self::assertSame($name, $this->reloadedImage());
        self::assertCount(1, (array) glob($this->kept.'/large/*'));
    }

    public function testThePictureStandsForItsSmallSizeWhenNoneIsGiven(): void
    {
        $this->write('card.png', base64_decode(self::PNG));

        $this->tester->execute(['file' => $this->csv([[$this->card->getExternalId(), 'card.png']], ['external_id', 'image_file'])]);

        $name = $this->reloadedImage();
        self::assertFileEquals($this->kept.'/large/'.$name, $this->kept.'/small/'.$name);

        // A small size given later takes its place, under the same name.
        $this->write('small.png', base64_decode(self::PNG).'smaller');
        $this->tester->execute(['file' => $this->csv([[$this->card->getExternalId(), 'card.png', 'small.png']])]);

        self::assertSame($name, $this->reloadedImage());
        self::assertStringEqualsFile($this->kept.'/small/'.$name, base64_decode(self::PNG).'smaller');
    }

    public function testANewPictureTakesThePlaceOfTheOneKeptBefore(): void
    {
        $this->write('first.png', base64_decode(self::PNG));
        $this->write('second.png', base64_decode(self::PNG).'another');
        $this->tester->execute(['file' => $this->csv([[$this->card->getExternalId(), 'first.png', '']])]);
        $first = $this->reloadedImage();

        $this->tester->execute(['file' => $this->csv([[$this->card->getExternalId(), 'second.png', '']])]);

        self::assertNotSame($first, $this->reloadedImage());
        self::assertFileDoesNotExist($this->kept.'/large/'.$first);
        self::assertFileDoesNotExist($this->kept.'/small/'.$first);
        self::assertFileExists($this->kept.'/large/'.$this->reloadedImage());
    }

    public function testRefusesWhatIsNotAPictureOfAKnownCardAndSaysWhere(): void
    {
        $this->write('card.png', base64_decode(self::PNG));
        $this->write('script.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $file = $this->csv([
            ['nobody-'.uniqid(), 'card.png', ''],
            [$this->card->getExternalId(), 'missing.png', ''],
            [$this->card->getExternalId(), 'script.png', ''],
            [$this->card->getExternalId(), 'card.png', 'script.png'],
            [$this->card->getExternalId(), '', ''],
        ]);

        self::assertSame(Command::FAILURE, $this->tester->execute(['file' => $file]));

        $display = $this->tester->getDisplay();
        self::assertMatchesRegularExpression('/Refused\s+5/', $display);
        self::assertStringContainsString('Line 2: No card', $display);
        self::assertStringContainsString('Line 3: There is no file', $display);
        self::assertStringContainsString('Line 4:', $display);
        self::assertNull($this->reloadedImage());
        self::assertDirectoryDoesNotExist($this->kept);
    }

    public function testFailsOnAFileThatIsNotTheExpectedList(): void
    {
        self::assertSame(Command::FAILURE, $this->tester->execute(['file' => $this->source.'/nothing.csv']));

        $file = $this->csv([[$this->card->getExternalId(), 'card.png']], ['card', 'picture']);
        self::assertSame(Command::FAILURE, $this->tester->execute(['file' => $file]));
        self::assertStringContainsString('external_id', $this->tester->getDisplay());
    }

    private function reloadedImage(): ?string
    {
        $this->em->clear();

        return $this->em->getRepository(Card::class)->find($this->card->getId())?->getCustomImage();
    }

    private function write(string $name, string $content): void
    {
        file_put_contents($this->source.'/pictures/'.$name, $content);
    }

    /**
     * @param list<list<string>> $lines
     * @param list<string>       $header
     */
    private function csv(array $lines, array $header = ['external_id', 'image_file', 'small_image_file']): string
    {
        $path = $this->source.'/pictures/list-'.bin2hex(random_bytes(4)).'.csv';
        $handle = fopen($path, 'w') ?: throw new \RuntimeException('Cannot write the list.');
        foreach ([$header, ...$lines] as $line) {
            fputcsv($handle, $line, escape: '');
        }
        fclose($handle);

        return $path;
    }
}
