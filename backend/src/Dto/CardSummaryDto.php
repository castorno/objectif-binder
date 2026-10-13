<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Card;
use App\Image\CardImages;

final readonly class CardSummaryDto implements \JsonSerializable
{
    /**
     * @param list<string>|null $languages
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $numberInSet,
        public ?string $rarity,
        public string $setName,
        public string $setCode,
        public string $gameSlug,
        public ?string $imageUrl,
        /** The languages the card was printed in; null when unknown. */
        public ?array $languages,
    ) {
    }

    /**
     * @param CardImages $images says where a browser should get the picture from
     */
    public static function fromEntity(Card $card, CardImages $images): self
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
            imageUrl: $images->addressOf($card),
            languages: $card->getLanguages(),
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
            'imageUrl' => $this->imageUrl,
            'languages' => $this->languages,
        ];
    }
}
