<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Exception\EmailAlreadyRegisteredException;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserRegistrationService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param string $email already normalized (trimmed, lower case)
     *
     * @throws EmailAlreadyRegisteredException
     */
    public function register(string $email, #[\SensitiveParameter] string $plainPassword): User
    {
        if (null !== $this->userRepository->findOneBy(['email' => $email])) {
            throw new EmailAlreadyRegisteredException();
        }

        $user = new User($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            // Two registrations for the same e-mail raced past the check
            // above: the unique index is what actually guarantees uniqueness.
            throw new EmailAlreadyRegisteredException($exception);
        }

        return $user;
    }
}
