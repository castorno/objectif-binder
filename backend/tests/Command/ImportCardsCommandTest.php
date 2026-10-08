<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\Game;
use App\Tests\Import\ImportFiles;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ImportCardsCommandTest extends KernelTestCase
{
    use ImportFiles;

    private EntityManagerInterface $em;
    private CommandTester $tester;
    private string $gameSlug;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->tester = new CommandTester(new Application($kernel)->find('app:import'));
        $this->gameSlug = 'import-'.bin2hex(random_bytes(4));

        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        $this->em->close();
        $this->removeImportFiles();

        parent::tearDown();
    }

    public function testImportsAFileAndPrintsWhatItDid(): void
    {
        $path = $this->jsonLinesFile([
            $this->cardRecord($this->gameSlug, '001', 'Ember Wyrm'),
            $this->cardRecord($this->gameSlug, '002', 'Frost Wyrm'),
            'not json',
        ]);

        $this->tester->execute(['file' => $path]);

        // Rejected lines do not make the import a failure: the rest is in.
        $this->tester->assertCommandIsSuccessful();
        $output = $this->tester->getDisplay();
        self::assertMatchesRegularExpression('/Created\s+2/', $output);
        self::assertMatchesRegularExpression('/Rejected\s+1/', $output);
        self::assertStringContainsString('Line 3: The line is not a JSON object.', $output);
        self::assertSame(1, $this->em->getRepository(Game::class)->count(['slug' => $this->gameSlug]));
    }

    public function testDryRunWritesNothing(): void
    {
        $path = $this->jsonLinesFile([$this->cardRecord($this->gameSlug, '001', 'Ember Wyrm')]);

        $this->tester->execute(['file' => $path, '--dry-run' => true]);

        $this->tester->assertCommandIsSuccessful();
        self::assertMatchesRegularExpression('/Created\s+1/', $this->tester->getDisplay());
        self::assertStringContainsString('nothing was written', $this->tester->getDisplay());
        self::assertSame(0, $this->em->getRepository(Game::class)->count(['slug' => $this->gameSlug]));
    }

    public function testFailsOnAFileItCannotRead(): void
    {
        $this->tester->execute(['file' => '/nowhere/cards.jsonl']);

        self::assertSame(Command::FAILURE, $this->tester->getStatusCode());
        self::assertStringContainsString('cannot be read', $this->tester->getDisplay());
    }
}
