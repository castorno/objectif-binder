<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidIdTrait;
use App\Repository\CardRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CardRepository::class)]
#[ORM\Table(name: 'card')]
#[ORM\UniqueConstraint(name: 'card_set_number_unique', columns: ['card_set_id', 'number_in_set'])]
class Card
{
    use UuidIdTrait;

    #[ORM\ManyToOne(targetEntity: CardSet::class)]
    #[ORM\JoinColumn(name: 'card_set_id', nullable: false)]
    private CardSet $cardSet;

    #[ORM\ManyToOne(targetEntity: Rarity::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Rarity $rarity = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    private string $name;

    /**
     * Not always purely numeric (e.g. "SWSH001", "TG01") — stored as a string.
     */
    #[ORM\Column(name: 'number_in_set', length: 20)]
    #[Assert\NotBlank]
    private string $numberInSet;

    /**
     * Canonical identifier from the import source (e.g. Pokémon TCG API's "base1-4"),
     * used to deduplicate during import. Nullable: hand-entered cards may not have one.
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $externalId = null;

    /**
     * Game-specific attributes (e.g. Pokémon types, Magic mana cost) that don't
     * warrant their own column shared across every game.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $attributes = [];

    public function __construct(CardSet $cardSet, string $name, string $numberInSet)
    {
        $this->id = Uuid::v7();
        $this->cardSet = $cardSet;
        $this->name = $name;
        $this->numberInSet = $numberInSet;
    }

    public function getCardSet(): CardSet
    {
        return $this->cardSet;
    }

    public function getRarity(): ?Rarity
    {
        return $this->rarity;
    }

    public function setRarity(?Rarity $rarity): static
    {
        $this->rarity = $rarity;

        return $this;
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

    public function getNumberInSet(): string
    {
        return $this->numberInSet;
    }

    public function setNumberInSet(string $numberInSet): static
    {
        $this->numberInSet = $numberInSet;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): static
    {
        $this->externalId = $externalId;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function setAttributes(array $attributes): static
    {
        $this->attributes = $attributes;

        return $this;
    }
}
