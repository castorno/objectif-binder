<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SeedDemoDataCommand;
use App\Entity\Card;
use App\Entity\Game;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedDemoDataCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->tester = new CommandTester(new Application($kernel)->find('app:demo:seed'));

        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        $this->em->close();

        parent::tearDown();
    }

    public function testSeedsDemoGameWithUniqueCardNames(): void
    {
        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();

        $names = $this->demoCardNamesBySet();

        self::assertCount(2, $names);
        foreach ($names as $setNames) {
            self::assertCount(60, $setNames);
            self::assertCount(60, array_unique($setNames));
        }
    }

    public function testRunningTwiceDoesNotDuplicateData(): void
    {
        $this->tester->execute([]);
        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();

        self::assertSame(1, $this->em->getRepository(Game::class)->count(['slug' => SeedDemoDataCommand::GAME_SLUG]));
        self::assertSame(120, array_sum(array_map(count(...), $this->demoCardNamesBySet())));
    }

    public function testSeedsAnIdentityPerCreatureAndLeavesOtherCardsWithoutOne(): void
    {
        $this->tester->execute([]);

        $cardsByIdentity = $this->demoCardCountByIdentity();

        // Six creatures, each under six names in each of the two sets.
        self::assertSame(
            ['Sentinelle' => 12, 'Golem' => 12, 'Oracle' => 12, 'Wyrm' => 12, 'Éclaireuse' => 12, 'Colosse' => 12],
            $cardsByIdentity,
        );
        // The 48 spells and relics have none.
        self::assertSame(120 - 72, $this->countDemoCardsWithoutIdentity());
        self::assertSame('Créatures', $this->em->getRepository(Game::class)->findOneBy(['slug' => SeedDemoDataCommand::GAME_SLUG])?->getIdentityLabel());
    }

    public function testGivesIdentitiesToADemoGameSeededBeforeTheyExisted(): void
    {
        $this->tester->execute([]);
        // Back to what an earlier version of the command left behind.
        $connection = $this->em->getConnection();
        $connection->executeStatement('DELETE FROM card_identity WHERE external_id LIKE :prefix', ['prefix' => 'demo-identity-%']);
        $connection->executeStatement('UPDATE game SET identity_label = NULL WHERE slug = :slug', ['slug' => SeedDemoDataCommand::GAME_SLUG]);
        $this->em->clear();
        self::assertSame(120, $this->countDemoCardsWithoutIdentity());

        $this->tester->execute([]);
        $this->tester->assertCommandIsSuccessful();
        $this->em->clear();

        self::assertSame(12, $this->demoCardCountByIdentity()['Wyrm']);
        self::assertSame(48, $this->countDemoCardsWithoutIdentity());
        self::assertSame(120, array_sum(array_map(count(...), $this->demoCardNamesBySet())));

        // And a third run changes nothing.
        $this->tester->execute([]);
        $this->em->clear();
        self::assertSame(72, array_sum($this->demoCardCountByIdentity()));
    }

    /**
     * @return array<string, int> number of demo cards by identity name, in the identities' order
     */
    private function demoCardCountByIdentity(): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('i.name AS name', 'COUNT(c.id) AS cards')
            ->from(Card::class, 'c')
            ->join('c.identities', 'i')
            ->join('i.game', 'g')
            ->where('g.slug = :slug')
            ->setParameter('slug', SeedDemoDataCommand::GAME_SLUG)
            ->groupBy('i.id')
            ->orderBy('i.sortOrder', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(intval(...), array_column($rows, 'cards', 'name'));
    }

    private function countDemoCardsWithoutIdentity(): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Card::class, 'c')
            ->join('c.cardSet', 's')
            ->join('s.game', 'g')
            ->where('g.slug = :slug')
            ->andWhere('c.identities IS EMPTY')
            ->setParameter('slug', SeedDemoDataCommand::GAME_SLUG)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, list<string>>
     */
    private function demoCardNamesBySet(): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('s.code AS code', 'c.name AS name')
            ->from(Card::class, 'c')
            ->join('c.cardSet', 's')
            ->join('s.game', 'g')
            ->where('g.slug = :slug')
            ->setParameter('slug', SeedDemoDataCommand::GAME_SLUG)
            ->getQuery()
            ->getArrayResult();

        $names = [];
        foreach ($rows as $row) {
            $names[$row['code']][] = $row['name'];
        }

        return $names;
    }
}
