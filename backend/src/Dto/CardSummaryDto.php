<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Card;

final readonly class CardSummaryDto implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public string $numberInSet,
        public ?string $rarity,
        public string $setName,
        public string $setCode,
        public string $gameSlug,
    ) {
    }

    public static function fromEntity(Card $card): self
    {
        $set = $card->getCardSet();

        return new self(
            id: (string) $card->getId(),
            name: $card->getName(),
            numberInSet: $card->getNumberInSet(),
            rarity: $card->getRarity()?->getName(),
            setName: $set->getName(),
            setCode: $set->getCode(),
            gameSlug: $set->getGame()->getSlug(),
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
            'numberInSet' => $this->numberInSet,
            'rarity' => $this->rarity,
            'setName' => $this->setName,
            'setCode' => $this->setCode,
            'gameSlug' => $this->gameSlug,
        ];
    }
}
