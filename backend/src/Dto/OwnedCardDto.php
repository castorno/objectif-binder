<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\OwnedCard;

/**
 * The copies of a card owned in one language. The card itself is not
 * repeated here: whoever reads this already knows which card it is about.
 */
final readonly class OwnedCardDto implements \JsonSerializable
{
    public function __construct(
        public string $language,
        public int $quantity,
        public ?string $condition,
        public string $acquiredAt,
    ) {
    }

    public static function fromEntity(OwnedCard $ownedCard): self
    {
        return new self(
            language: $ownedCard->getLanguage(),
            quantity: $ownedCard->getQuantity(),
            condition: $ownedCard->getCondition()?->value,
            acquiredAt: $ownedCard->getAcquiredAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'language' => $this->language,
            'quantity' => $this->quantity,
            'condition' => $this->condition,
            'acquiredAt' => $this->acquiredAt,
        ];
    }
}
