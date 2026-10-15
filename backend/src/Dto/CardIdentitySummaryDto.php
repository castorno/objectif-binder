<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\CardIdentity;

/**
 * An identity as an entry of the grouped catalog, with how many cards it groups,
 * the picture of the first of them, and that of the identity itself, which a
 * stand-in shows (it stands for what is missing, not for a card).
 */
final readonly class CardIdentitySummaryDto implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public ?int $sortOrder,
        public string $gameSlug,
        public int $cardCount,
        public ?string $imageUrl,
        public ?string $identityImageUrl,
    ) {
    }

    public static function fromEntity(CardIdentity $identity, int $cardCount, ?string $imageUrl, ?string $identityImageUrl): self
    {
        return new self(
            id: (string) $identity->getId(),
            name: $identity->getName(),
            sortOrder: $identity->getSortOrder(),
            gameSlug: $identity->getGame()->getSlug(),
            cardCount: $cardCount,
            imageUrl: $imageUrl,
            identityImageUrl: $identityImageUrl,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sortOrder' => $this->sortOrder,
            'gameSlug' => $this->gameSlug,
            'cardCount' => $this->cardCount,
            'imageUrl' => $this->imageUrl,
            'identityImageUrl' => $this->identityImageUrl,
        ];
    }
}
