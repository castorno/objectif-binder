<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidIdTrait;
use App\Repository\CardSetRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CardSetRepository::class)]
#[ORM\Table(name: 'card_set')]
#[ORM\UniqueConstraint(name: 'card_set_game_code_unique', columns: ['game_id', 'code'])]
class CardSet
{
    use UuidIdTrait;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Game $game;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private string $name;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private string $code;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $releaseDate = null;

    /**
     * The set this one comes with, when its cards are found in the boosters
     * of another: a gallery or a classic collection released inside a main
     * set. One level only: a parent has no parent of its own. Sources list
     * such sets apart and do not say they belong together, so the link is
     * entered by an administrator and no import touches it.
     */
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_id', nullable: true, onDelete: 'SET NULL')]
    private ?CardSet $parent = null;

    public function __construct(Game $game, string $name, string $code)
    {
        $this->id = Uuid::v7();
        $this->game = $game;
        $this->name = $name;
        $this->code = $code;
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getReleaseDate(): ?\DateTimeImmutable
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(?\DateTimeImmutable $releaseDate): static
    {
        $this->releaseDate = $releaseDate;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * Checked by CardSetParentService, which is the way to change it.
     */
    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * The set whose boosters hold the cards of this one: its parent, or itself.
     */
    public function getMainSet(): self
    {
        return $this->parent ?? $this;
    }
}
