<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The only way to make an administrator. No route of the API gives the
 * role: whoever can run this command already has the server.
 */
#[AsCommand(
    name: 'app:user:promote',
    description: 'Makes an existing user an administrator, or takes the role back with --demote.',
)]
final class PromoteUserCommand
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'E-mail address of the account')]
        string $email,
        #[Option(description: 'Take the administrator role back instead of giving it')]
        bool $demote = false,
    ): int {
        $user = $this->userRepository->loadUserByIdentifier($email);
        if (null === $user) {
            $io->error(sprintf('No account with the address "%s".', $email));

            return Command::FAILURE;
        }

        // ROLE_USER is implied for everyone and never stored.
        $roles = array_values(array_diff($user->getRoles(), ['ROLE_USER', User::ROLE_ADMIN]));
        if (!$demote) {
            $roles[] = User::ROLE_ADMIN;
        }
        $user->setRoles($roles);
        $this->entityManager->flush();

        $io->success(sprintf($demote ? '%s is no longer an administrator.' : '%s is now an administrator.', $user->getEmail()));

        return Command::SUCCESS;
    }
}
