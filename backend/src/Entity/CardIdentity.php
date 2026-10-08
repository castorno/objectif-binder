<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidIdTrait;
use App\Repository\CardIdentityRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What a card depicts or is, beyond one printing of it: a creature that comes
 * back from set to set, or the rules card several printings share. Cards of
 * a game are grouped by it ("every card of this creature").
 *
 * The meaning is the game's own and comes with the imported data; nothing
 * here is specific to one game.
 */
#[ORM\Entity(repositoryClass: CardIdentityRepository::class)]
#[ORM\Table(name: 'card_identity')]
#[ORM\UniqueConstraint(name: 'card_identity_game_external_id_unique', columns: ['game_id', 'external_id'])]
class CardIdentity
{
    use UuidIdTrait;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Game $game;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    private string $name;

    /**
     * Identifier in the import source, unique within the game: what tells an
     * import that it already knows this identity.
     */
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $externalId;

    /**
     * Position in the game's own numbering, when it has one. Null otherwise:
     * such identities are listed by name.
     */
    #[ORM\Column(nullable: true)]
    private ?int $sortOrder = null;

    public function __construct(Game $game, string $name, string $externalId)
    {
        $this->id = Uuid::v7();
        $this->game = $game;
        $this->name = $name;
        $this->externalId = $externalId;
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

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getSortOrder(): ?int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(?int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }
}
