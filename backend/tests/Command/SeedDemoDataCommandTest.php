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
