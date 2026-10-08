<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Card;
use App\Entity\OwnedCard;

/**
 * One item of the collection list: a card and the copies owned of it, one
 * entry per language.
 */
final readonly class CollectionEntryDto implements \JsonSerializable
{
    /**
     * @param list<OwnedCardDto> $owned
     */
    public function __construct(
        public CardSummaryDto $card,
        public array $owned,
    ) {
    }

    /**
     * @param list<OwnedCard> $ownedCards
     */
    public static function fromEntities(Card $card, array $ownedCards): self
    {
        return new self(
            card: CardSummaryDto::fromEntity($card),
            owned: array_map(OwnedCardDto::fromEntity(...), $ownedCards),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'card' => $this->card,
            'owned' => $this->owned,
        ];
    }
}
