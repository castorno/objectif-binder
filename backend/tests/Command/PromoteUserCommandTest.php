<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PromoteUserCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CommandTester $tester;
    private User $user;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->tester = new CommandTester(new Application($kernel)->find('app:user:promote'));
        $this->em->getConnection()->beginTransaction();

        $this->user = new User('promote-'.uniqid().'@example.com')->setPassword('not-a-real-hash');
        $this->em->persist($this->user);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        $this->em->close();

        parent::tearDown();
    }

    public function testMakesAUserAnAdministratorThenTakesTheRoleBack(): void
    {
        // The address is found whatever the case it is typed in.
        self::assertSame(Command::SUCCESS, $this->tester->execute(['email' => strtoupper($this->user->getEmail())]));
        self::assertContains(User::ROLE_ADMIN, $this->reloadedRoles());

        // Asked twice, the role is still there once.
        $this->tester->execute(['email' => $this->user->getEmail()]);
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $this->reloadedRoles());

        self::assertSame(Command::SUCCESS, $this->tester->execute(['email' => $this->user->getEmail(), '--demote' => true]));
        self::assertSame(['ROLE_USER'], $this->reloadedRoles());
    }

    public function testFailsForAnAddressWithoutAccount(): void
    {
        self::assertSame(Command::FAILURE, $this->tester->execute(['email' => 'nobody@example.com']));
        self::assertStringContainsString('No account', $this->tester->getDisplay());
    }

    /**
     * @return list<string>
     */
    private function reloadedRoles(): array
    {
        $this->em->clear();
        $roles = $this->em->getRepository(User::class)->find($this->user->getId())?->getRoles() ?? [];
        sort($roles);

        return $roles;
    }
}
