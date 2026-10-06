<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used by the security layer at login and on every authenticated request.
     * E-mails are stored in lower case, so the lookup ignores the case typed.
     */
    public function loadUserByIdentifier(string $identifier): ?User
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($identifier))]);
    }
}
