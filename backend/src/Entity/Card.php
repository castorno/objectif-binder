<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidIdTrait;
use App\Repository\CardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    /** Name of the database collation that sorts the digits inside a text as numbers. */
    public const string NUMBER_COLLATION = 'natural_sort';

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
     *
     * Sorted the way a person reads it: 2 before 10, "TG2" before "TG10".
     * Plain text order would put 10 and 100 before 2. The rule is a
     * collation of the database (see the migration that creates it), so
     * every query sorting on this column follows it without saying so.
     * It only changes the order: "1" and "01" stay two different numbers.
     */
    #[ORM\Column(name: 'number_in_set', length: 20, options: ['collation' => self::NUMBER_COLLATION])]
    #[Assert\NotBlank]
    private string $numberInSet;

    /**
     * Canonical identifier from the import source (e.g. Pokémon TCG API's "base1-4"),
     * used to deduplicate during import. Nullable: hand-entered cards may not have one.
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $externalId = null;

    /**
     * Address of a picture of the card, served by someone else. Only the
     * address is kept: the project stores and redistributes no artwork.
     * Null for most cards, which get a generated stand-in.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageUrl = null;

    /**
     * The same picture, larger, for the page of the card.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $largeImageUrl = null;

    /**
     * Game-specific attributes (e.g. Pokémon types, Magic mana cost) that don't
     * warrant their own column shared across every game.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $attributes = [];

    /**
     * What the card depicts or is (see CardIdentity). Several for a card
     * showing more than one, none for a card the game does not group.
     *
     * @var Collection<int, CardIdentity>
     */
    #[ORM\ManyToMany(targetEntity: CardIdentity::class)]
    #[ORM\JoinTable(name: 'card_identity_link')]
    private Collection $identities;

    public function __construct(CardSet $cardSet, string $name, string $numberInSet)
    {
        $this->id = Uuid::v7();
        $this->identities = new ArrayCollection();
        $this->cardSet = $cardSet;
        $this->name = $name;
        $this->numberInSet = $numberInSet;
    }

    public function getCardSet(): CardSet
    {
        return $this->cardSet;
    }

    /**
     * @return Collection<int, CardIdentity>
     */
    public function getIdentities(): Collection
    {
        return $this->identities;
    }

    public function addIdentity(CardIdentity $identity): static
    {
        if ($identity->getGame() !== $this->cardSet->getGame()) {
            throw new \InvalidArgumentException('A card can only take an identity of its own game.');
        }

        if (!$this->identities->contains($identity)) {
            $this->identities->add($identity);
        }

        return $this;
    }

    public function removeIdentity(CardIdentity $identity): static
    {
        $this->identities->removeElement($identity);

        return $this;
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

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function getLargeImageUrl(): ?string
    {
        return $this->largeImageUrl;
    }

    public function setLargeImageUrl(?string $largeImageUrl): static
    {
        $this->largeImageUrl = $largeImageUrl;

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
