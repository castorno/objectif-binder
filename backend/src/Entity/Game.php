<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidIdTrait;
use App\Repository\GameRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\Table(name: 'game')]
class Game
{
    use UuidIdTrait;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(-[a-z0-9]+)*$/', message: 'Slug must be lowercase, alphanumeric, hyphen-separated.')]
    private string $slug;

    /**
     * What this game calls the identities its cards are grouped by (see
     * CardIdentity), as shown to users. Null for a game without any.
     */
    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $identityLabel = null;

    /**
     * What this game calls the groups its identities are sorted into (see
     * CardIdentity::$groupName), as shown to users. Null for a game without any.
     */
    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $identityGroupLabel = null;

    public function __construct(string $name, string $slug)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->slug = $slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getIdentityLabel(): ?string
    {
        return $this->identityLabel;
    }

    public function setIdentityLabel(?string $identityLabel): static
    {
        $this->identityLabel = $identityLabel;

        return $this;
    }

    public function getIdentityGroupLabel(): ?string
    {
        return $this->identityGroupLabel;
    }

    public function setIdentityGroupLabel(?string $identityGroupLabel): static
    {
        $this->identityGroupLabel = $identityGroupLabel;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }
}
