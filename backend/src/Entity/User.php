<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidIdTrait;
use App\Enum\CardCondition;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use UuidIdTrait;

    /** Opens the administration. Only ever given by the app:user:promote command. */
    public const string ROLE_ADMIN = 'ROLE_ADMIN';

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    /**
     * The condition the user's cards are usually in: what a card they add
     * starts with. A preference, applied by the interface when it adds a
     * card; the API never fills a condition in by itself. Null: none.
     */
    #[ORM\Column(length: 50, nullable: true, enumType: CardCondition::class)]
    private ?CardCondition $defaultCondition = null;

    public function __construct(string $email)
    {
        $this->id = Uuid::v7();
        $this->email = $email;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        // Never empty: registration validates it, and the column is not nullable.
        \assert('' !== $this->email);

        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getDefaultCondition(): ?CardCondition
    {
        return $this->defaultCondition;
    }

    public function setDefaultCondition(?CardCondition $defaultCondition): static
    {
        $this->defaultCondition = $defaultCondition;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }
}
