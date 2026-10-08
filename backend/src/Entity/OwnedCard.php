<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\UuidIdTrait;
use App\Enum\CardCondition;
use App\Repository\OwnedCardRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A card owned by a user, tracked per language: owning the same card in
 * French and Japanese produces two rows, each with its own quantity.
 */
#[ORM\Entity(repositoryClass: OwnedCardRepository::class)]
#[ORM\Table(name: 'owned_card')]
#[ORM\UniqueConstraint(name: 'owned_card_user_card_language_unique', columns: ['user_id', 'card_id', 'language'])]
class OwnedCard
{
    use UuidIdTrait;

    /**
     * A collection does not outlive its owner: deleting the account removes it.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Card::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Card $card;

    /**
     * ISO 639-1 language code of the printing (e.g. "en", "fr", "ja").
     */
    #[ORM\Column(length: 2)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z]{2}$/', message: 'Language must be an ISO 639-1 code.')]
    private string $language;

    #[ORM\Column]
    #[Assert\Positive]
    private int $quantity = 1;

    /**
     * Shared by every copy of this card in this language. Null: not specified.
     */
    #[ORM\Column(length: 50, nullable: true, enumType: CardCondition::class)]
    private ?CardCondition $condition = null;

    #[ORM\Column]
    private \DateTimeImmutable $acquiredAt;

    public function __construct(User $user, Card $card, string $language, int $quantity = 1)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->card = $card;
        $this->language = $language;
        $this->quantity = $quantity;
        $this->acquiredAt = new \DateTimeImmutable();
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCard(): Card
    {
        return $this->card;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getCondition(): ?CardCondition
    {
        return $this->condition;
    }

    public function setCondition(?CardCondition $condition): static
    {
        $this->condition = $condition;

        return $this;
    }

    public function getAcquiredAt(): \DateTimeImmutable
    {
        return $this->acquiredAt;
    }
}
