<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidIdTrait;
use App\Repository\RarityRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RarityRepository::class)]
#[ORM\Table(name: 'rarity')]
#[ORM\UniqueConstraint(name: 'rarity_game_name_unique', columns: ['game_id', 'name'])]
class Rarity
{
    use UuidIdTrait;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Game $game;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name;

    #[ORM\Column]
    private int $sortOrder = 0;

    public function __construct(Game $game, string $name, int $sortOrder = 0)
    {
        $this->id = Uuid::v7();
        $this->game = $game;
        $this->name = $name;
        $this->sortOrder = $sortOrder;
    }

    public function getGame(): Game
    {
        return $this->game;
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

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }
}
