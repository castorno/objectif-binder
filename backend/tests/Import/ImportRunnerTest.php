<?php

declare(strict_types=1);

namespace App\Tests\Import;

use App\Entity\Card;
use App\Entity\CardIdentity;
use App\Entity\CardSet;
use App\Entity\Game;
use App\Entity\ImportRun;
use App\Entity\Rarity;
use App\Enum\CardFinish;
use App\Enum\ImportRunStatus;
use App\Import\CardImporter;
use App\Import\Exception\ImportAlreadyRunningException;
use App\Import\Exception\UnreadableSourceException;
use App\Import\ImportedCardFactory;
use App\Import\ImportReport;
use App\Import\ImportRunner;
use App\Import\Reader\RecordReader;
use App\Import\Reader\SourceRecord;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Lock\LockFactory;

final class ImportRunnerTest extends KernelTestCase
{
    use ImportFiles;

    private EntityManagerInterface $em;
    private ImportRunner $runner;
    private string $gameSlug;

    protected function setUp(): void
    {
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->runner = static::getContainer()->get(ImportRunner::class);
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

    public function testCreatesEverythingARecordDescribes(): void
    {
        $path = $this->jsonLinesFile([
            $this->cardRecord($this->gameSlug, '001', 'Ember Wyrm'),
            $this->cardRecord($this->gameSlug, '002', 'Gust Charm', ['rarity' => null, 'externalId' => null, 'attributes' => null, 'identities' => null]),
        ]);

        $report = $this->runner->run($path);

        self::assertSame(ImportRunStatus::Completed, $report->getStatus());
        self::assertSame([2, 0, 0, 0], $this->counts($report));

        $game = $this->game();
        self::assertSame('Import Test', $game->getName());
        self::assertSame('Creatures', $game->getIdentityLabel());

        $set = $this->em->getRepository(CardSet::class)->findOneBy(['game' => $game, 'code' => 'IT1']);
        self::assertNotNull($set);
        self::assertSame('First Set', $set->getName());
        self::assertSame('2025-03-01', $set->getReleaseDate()?->format('Y-m-d'));

        $wyrm = $this->card('001');
        self::assertSame('Ember Wyrm', $wyrm->getName());
        self::assertSame('Common', $wyrm->getRarity()?->getName());
        self::assertSame('it1-001', $wyrm->getExternalId());
        self::assertSame(['type' => 'Creature'], $wyrm->getAttributes());
        self::assertSame(['Wyrm'], $this->identityNames($wyrm));
        self::assertSame(1, ($wyrm->getIdentities()->first() ?: null)?->getSortOrder());

        $charm = $this->card('002');
        self::assertNull($charm->getRarity());
        self::assertNull($charm->getExternalId());
        self::assertSame([], $charm->getAttributes());
        self::assertSame([], $this->identityNames($charm));
    }

    /**
     * The property the whole import relies on: the same file, imported
     * again, changes nothing. It is also how an interrupted import resumes.
     */
    public function testImportingTheSameFileAgainChangesNothing(): void
    {
        $path = $this->jsonLinesFile([
            $this->cardRecord($this->gameSlug, '001', 'Ember Wyrm'),
            $this->cardRecord($this->gameSlug, '002', 'Frost Wyrm', ['rarity' => 'Rare']),
        ]);

        $this->runner->run($path);
        $report = $this->runner->run($path);

        self::assertSame([0, 0, 2, 0], $this->counts($report));
        self::assertSame(1, $this->em->getRepository(Game::class)->count(['slug' => $this->gameSlug]));
        self::assertSame(1, $this->em->getRepository(CardSet::class)->count(['game' => $this->game()]));
        self::assertSame(2, $this->em->getRepository(Rarity::class)->count(['game' => $this->game()]));
        self::assertSame(1, $this->em->getRepository(CardIdentity::class)->count(['game' => $this->game()]));
        self::assertSame(2, $this->cardCount());
    }

    public function testUpdatesACardThatChangedInTheSource(): void
    {
        $this->runner->run($this->jsonLinesFile([
            $this->cardRecord($this->gameSlug, '001', 'Ember Wyrm'),
            $this->cardRecord($this->gameSlug, '002', 'Frost Wyrm'),
        ]));

        $report = $this->runner->run($this->jsonLinesFile([
            $this->cardRecord($this->gameSlug, '001', 'Ember Wyrm, Reborn', [
                'rarity' => 'Rare',
                'identities' => [['externalId' => 'phoenix', 'name' => 'Phoenix']],
            ]),
            $this->cardRecord($this->gameSlug, '002', 'Frost Wyrm'),
        ]));

        self::assertSame([0, 1, 1, 0], $this->counts($report));

        $card = $this->card('001');
        self::assertSame('Ember Wyrm, Reborn', $card->getName());
        self::assertSame('Rare', $card->getRarity()?->getName());
        // The source is the reference: an identity it no longer lists goes.
        self::assertSame(['Phoenix'], $this->identityNames($card));
        self::assertSame(2, $this->cardCount());
    }

    /**
     * The source is the reference for pictures too: a file that stops
     * giving them takes them off the cards.
     */
    public function testAddsThenRemovesThePicturesOfACard(): void
    {
        $withPictures = $this->cardRecord($this->gameSlug, '001', 'Ember Wyrm', [
            'imageUrl' => 'https://images.example.org/it1/1/low.webp',
            'largeImageUrl' => 'https://images.example.org/it1/1/high.webp',
        ]);

        $report = $this->runner->run($this->jsonLinesFile([$withPictures]));
        self::assertSame([1, 0, 0, 0], $this->counts($report));
        self::assertSame('https://images.example.org/it1/1/low.webp', $this->card('001')->getImageUrl());
        self::assertSame('https://images.example.org/it1/1/high.webp', $this->card('001')->getLargeImageUrl());

        $report = $this->runner->run($this->jsonLinesFile([$this->cardRecord($this->gameSlug, '001', 'Ember Wyrm')]));
        self::assertSame([0, 1, 0, 0], $this->counts($report));
        self::assertNull($this->card('001')->getImageUrl());
        self::assertNull($this->card('001')->getLargeImageUrl());
    }

    public function testSkipsTheRecordsItCannotImportAndKeepsTheOthers(): void
    {
        $path = $this->jsonLinesFile([
            $this->cardRecord($this->gameSlug, '001', 'Ember Wyrm'),
            '{"name": "broken',
            $this->cardRecord($this->gameSlug, '003', '', ['number' => str_repeat('9', 30)]),
            $this->cardRecord($this->gameSlug, '004', 'Frost Wyrm'),
        ]);

        $report = $this->runner->run($path);

        self::assertSame(ImportRunStatus::Completed, $report->getStatus());
        self::assertSame([2, 0, 0, 2], $this->counts($report));
        self::assertSame([2, 3], array_column($report->getErrors(), 'position'));
        self::assertCount(2, $report->getErrors()[1]['messages']);
        self::assertSame(2, $this->cardCount());
    }

    /**
     * Between two batches the importer forgets every entity it knew. The
     * second batch must find the set the first one created, not create it
     * again.
     */
    public function testACatalogSpreadOverSeveralBatchesIsNotDuplicated(): void
    {
        $lines = [];
        foreach (range(1, 7) as $number) {
            $lines[] = $this->cardRecord($this->gameSlug, sprintf('%03d', $number), 'Wyrm '.$number, ['rarity' => $number % 2 ? 'Common' : 'Rare']);
        }

        $report = $this->runner->run($this->jsonLinesFile($lines), batchSize: 3);

        self::assertSame([7, 0, 0, 0], $this->counts($report));
        self::assertSame(1, $this->em->getRepository(CardSet::class)->count(['game' => $this->game()]));
        self::assertSame(2, $this->em->getRepository(Rarity::class)->count(['game' => $this->game()]));
        self::assertSame(1, $this->em->getRepository(CardIdentity::class)->count(['game' => $this->game()]));
        self::assertSame(7, $this->cardCount());
    }

    public function testACardListedTwiceInAFileIsImportedOnce(): void
    {
        $report = $this->runner->run($this->jsonLinesFile([
            $this->cardRecord($this->gameSlug, '001', 'Ember Wyrm'),
            $this->cardRecord($this->gameSlug, '001', 'Ember Wyrm, corrected'),
        ]));

        // The second line is an update of the card the first one created.
        self::assertSame([1, 1, 0, 0], $this->counts($report));
        self::assertSame(1, $this->cardCount());
        self::assertSame('Ember Wyrm, corrected', $this->card('001')->getName());
    }

    /**
     * A CSV file and a JSON Lines file describing the same card are the same
     * card to the import: the format stops mattering once a line is read.
     */
    public function testACsvFileImportsLikeAJsonLinesOne(): void
    {
        $this->runner->run($this->jsonLinesFile([$this->cardRecord($this->gameSlug, '001', 'Ember Wyrm')]));

        $report = $this->runner->run($this->importFile(
            "game_slug,game_name,game_identity_label,set_code,set_name,set_release_date,number,name,rarity,external_id,identity_ids,identity_names,identity_sort_orders,attribute:type\n"
            ."{$this->gameSlug},Import Test,Creatures,IT1,First Set,2025-03-01,001,Ember Wyrm,Common,it1-001,wyrm,Wyrm,1,Creature\n"
            ."{$this->gameSlug},Import Test,,IT1,First Set,,002,Frost Wyrm,Rare,,,,,\n"
            ."{$this->gameSlug},Import Test,,IT1,First Set,,003,,Rare,,,,,\n",
            'csv',
        ));

        self::assertSame([1, 0, 1, 1], $this->counts($report));
        self::assertSame(4, $report->getErrors()[0]['position']);
        self::assertStringStartsWith('name: ', $report->getErrors()[0]['messages'][0]);
        self::assertSame('Frost Wyrm', $this->card('002')->getName());
    }

    public function testNewRaritiesAreRankedAfterTheExistingOnesInOrderOfAppearance(): void
    {
        $this->runner->run($this->jsonLinesFile([
            $this->cardRecord($this->gameSlug, '001', 'Ember Wyrm', ['rarity' => 'Common']),
            $this->cardRecord($this->gameSlug, '002', 'Frost Wyrm', ['rarity' => 'Rare']),
        ]));
        $this->runner->run($this->jsonLinesFile([
            $this->cardRecord($this->gameSlug, '003', 'Storm Wyrm', ['rarity' => 'Mythic']),
        ]));

        $rarities = $this->em->getRepository(Rarity::class)->findBy(['game' => $this->game()], ['sortOrder' => 'ASC']);

        self::assertSame(['Common', 'Rare', 'Mythic'], array_map(static fn (Rarity $rarity): string => $rarity->getName(), $rarities));
        self::assertSame([0, 1, 2], array_map(static fn (Rarity $rarity): int => $rarity->getSortOrder(), $rarities));
    }

    public function testLeavingAnOptionalLabelOutKeepsTheOneAlreadyThere(): void
    {
        $this->runner->run($this->jsonLinesFile([$this->cardRecord($this->gameSlug, '001', 'Ember Wyrm')]));
        $this->runner->run($this->jsonLinesFile([
            $this->cardRecord($this->gameSlug, '002', 'Frost Wyrm', [
                'game' => ['slug' => $this->gameSlug, 'name' => 'Import Test'],
                'identities' => [['externalId' => 'wyrm', 'name' => 'Wyrm']],
            ]),
        ]));

        self::assertSame('Creatures', $this->game()->getIdentityLabel());
        self::assertSame(1, $this->em->getRepository(CardIdentity::class)->findOneBy(['game' => $this->game(), 'externalId' => 'wyrm'])?->getSortOrder());
    }

    public function testRecordsTheFinishesOfACardAndKeepsThemWhenLeftOut(): void
    {
        $this->runner->run($this->jsonLinesFile([$this->cardRecord($this->gameSlug, '001', 'Ember Wyrm', ['finishes' => ['normal', 'reverse']])]));
        self::assertSame([CardFinish::Normal, CardFinish::Reverse], $this->card('001')->getFinishes());

        // The same card from a source that knows nothing about finishes.
        $report = $this->runner->run($this->jsonLinesFile([$this->cardRecord($this->gameSlug, '001', 'Ember Wyrm')]));
        self::assertSame([0, 0, 1, 0], $this->counts($report));
        self::assertSame([CardFinish::Normal, CardFinish::Reverse], $this->card('001')->getFinishes());

        $report = $this->runner->run($this->jsonLinesFile([$this->cardRecord($this->gameSlug, '001', 'Ember Wyrm', ['finishes' => ['holo']])]));
        self::assertSame([0, 1, 0, 0], $this->counts($report));
        self::assertSame([CardFinish::Holo], $this->card('001')->getFinishes());
    }

    public function testSortsIdentitiesIntoTheGroupsTheSourceNames(): void
    {
        $withGroup = ['externalId' => 'wyrm', 'name' => 'Wyrm', 'group' => ['name' => 'First era', 'order' => 1]];
        $this->runner->run($this->jsonLinesFile([
            $this->cardRecord($this->gameSlug, '001', 'Ember Wyrm', [
                'game' => ['slug' => $this->gameSlug, 'name' => 'Import Test', 'identityGroupLabel' => 'Era'],
                'identities' => [$withGroup],
            ]),
        ]));

        $wyrm = fn (): ?CardIdentity => $this->em->getRepository(CardIdentity::class)->findOneBy(['game' => $this->game(), 'externalId' => 'wyrm']);
        self::assertSame('Era', $this->game()->getIdentityGroupLabel());
        self::assertSame('First era', $wyrm()?->getGroupName());
        self::assertSame(1, $wyrm()?->getGroupOrder());

        // A record that names no group leaves the identity where it is.
        $this->runner->run($this->jsonLinesFile([$this->cardRecord($this->gameSlug, '002', 'Frost Wyrm')]));
        self::assertSame('First era', $wyrm()?->getGroupName());
        self::assertSame('Era', $this->game()->getIdentityGroupLabel());

        // One that names another moves it.
        $this->runner->run($this->jsonLinesFile([
            $this->cardRecord($this->gameSlug, '003', 'Storm Wyrm', ['identities' => [['group' => ['name' => 'Second era', 'order' => 2]] + $withGroup]]),
        ]));
        self::assertSame('Second era', $wyrm()?->getGroupName());
        self::assertSame(2, $wyrm()?->getGroupOrder());
    }

    public function testKeepsATraceOfEachImport(): void
    {
        $path = $this->jsonLinesFile([$this->cardRecord($this->gameSlug, '001', 'Ember Wyrm'), 'not json']);

        $this->runner->run($path);

        $run = $this->em->getRepository(ImportRun::class)->findOneBy(['source' => basename($path)]);
        self::assertNotNull($run);
        self::assertSame(ImportRunStatus::Completed, $run->getStatus());
        self::assertNotNull($run->getFinishedAt());
        self::assertSame(1, $run->getCreatedCount());
        self::assertSame(1, $run->getRejectedCount());
        self::assertSame(2, $run->getErrors()[0]['position']);
        self::assertNull($run->getFailure());
    }

    public function testADryRunReportsWhatWouldChangeAndWritesNothing(): void
    {
        $this->runner->run($this->jsonLinesFile([$this->cardRecord($this->gameSlug, '001', 'Ember Wyrm')]));
        $path = $this->jsonLinesFile([
            $this->cardRecord($this->gameSlug, '001', 'Ember Wyrm, Reborn'),
            $this->cardRecord($this->gameSlug, '002', 'Frost Wyrm'),
            'not json',
        ]);

        $report = $this->runner->run($path, dryRun: true);

        self::assertTrue($report->dryRun);
        self::assertSame(ImportRunStatus::Completed, $report->getStatus());
        self::assertSame([1, 1, 0, 1], $this->counts($report));
        self::assertSame(1, $this->cardCount());
        self::assertSame('Ember Wyrm', $this->card('001')->getName());
        self::assertNull($this->em->getRepository(ImportRun::class)->findOneBy(['source' => basename($path)]));
    }

    /**
     * When the import stops half-way, what was already written stays, and
     * the trace says the import failed.
     */
    public function testAFailureKeepsTheBatchesAlreadyWrittenAndIsRecorded(): void
    {
        $records = [
            SourceRecord::read(1, $this->cardRecord($this->gameSlug, '001', 'Ember Wyrm')),
            SourceRecord::read(2, $this->cardRecord($this->gameSlug, '002', 'Frost Wyrm')),
            SourceRecord::read(3, $this->cardRecord($this->gameSlug, '003', 'Storm Wyrm')),
        ];
        $failingReader = new class($records) implements RecordReader {
            /**
             * @param list<SourceRecord> $records
             */
            public function __construct(private readonly array $records)
            {
            }

            public function supports(string $path): bool
            {
                return true;
            }

            public function read(string $path): iterable
            {
                yield from $this->records;

                throw new \RuntimeException('The source went away.');
            }
        };
        $container = static::getContainer();
        $runner = new ImportRunner(
            [$failingReader],
            $container->get(ImportedCardFactory::class),
            $container->get(CardImporter::class),
            $this->em,
            $container->get(ManagerRegistry::class),
            $container->get(LockFactory::class),
            new NullLogger(),
        );

        $report = $runner->run('remote-source', batchSize: 2);

        self::assertSame(ImportRunStatus::Failed, $report->getStatus());
        self::assertSame('The source went away.', $report->getFailure());
        // The first batch of two was written; the third card was not.
        self::assertSame(2, $this->cardCount());

        $run = $this->em->getRepository(ImportRun::class)->findOneBy(['source' => 'remote-source']);
        self::assertSame(ImportRunStatus::Failed, $run?->getStatus());
        self::assertSame('The source went away.', $run?->getFailure());
    }

    public function testRefusesToRunWhileAnotherImportIs(): void
    {
        $lock = static::getContainer()->get(LockFactory::class)->createLock('card-import');
        self::assertTrue($lock->acquire());

        try {
            $this->expectException(ImportAlreadyRunningException::class);

            $this->runner->run($this->jsonLinesFile([$this->cardRecord($this->gameSlug, '001', 'Ember Wyrm')]));
        } finally {
            $lock->release();
        }
    }

    public function testRefusesAFileWhoseFormatItDoesNotKnow(): void
    {
        $this->expectException(UnreadableSourceException::class);

        $this->runner->run($this->jsonLinesFile([$this->cardRecord($this->gameSlug, '001', 'Ember Wyrm')], 'xml'));
    }

    /**
     * @return array{int, int, int, int} created, updated, unchanged, rejected
     */
    private function counts(ImportReport $report): array
    {
        return [$report->getCreated(), $report->getUpdated(), $report->getUnchanged(), $report->getRejected()];
    }

    private function game(): Game
    {
        $game = $this->em->getRepository(Game::class)->findOneBy(['slug' => $this->gameSlug]);
        self::assertNotNull($game);

        return $game;
    }

    private function card(string $number): Card
    {
        $card = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Card::class, 'c')
            ->join('c.cardSet', 's')
            ->where('s.game = :game')
            ->andWhere('c.numberInSet = :number')
            ->setParameter('game', $this->game())
            ->setParameter('number', $number)
            ->getQuery()
            ->getOneOrNullResult();
        self::assertInstanceOf(Card::class, $card);

        return $card;
    }

    private function cardCount(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Card::class, 'c')
            ->join('c.cardSet', 's')
            ->join('s.game', 'g')
            ->where('g.slug = :slug')
            ->setParameter('slug', $this->gameSlug)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<string>
     */
    private function identityNames(Card $card): array
    {
        return array_values(array_map(static fn (CardIdentity $identity): string => $identity->getName(), $card->getIdentities()->toArray()));
    }
}
