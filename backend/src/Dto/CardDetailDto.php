<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Card;

final readonly class CardDetailDto implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $numberInSet,
        public ?string $externalId,
        public array $attributes,
        public ?string $rarity,
        public string $setName,
        public string $setCode,
        public string $gameSlug,
        public ?int $pullOddsOneIn,
    ) {
    }

    public static function fromEntity(Card $card, ?int $pullOddsOneIn): self
    {
        $set = $card->getCardSet();

        return new self(
            id: (string) $card->getId(),
            name: $card->getName(),
            numberInSet: $card->getNumberInSet(),
            externalId: $card->getExternalId(),
            attributes: $card->getAttributes(),
            rarity: $card->getRarity()?->getName(),
            setName: $set->getName(),
            setCode: $set->getCode(),
            gameSlug: $set->getGame()->getSlug(),
            pullOddsOneIn: $pullOddsOneIn,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'numberInSet' => $this->numberInSet,
            'externalId' => $this->externalId,
            'attributes' => $this->attributes,
            'rarity' => $this->rarity,
            'setName' => $this->setName,
            'setCode' => $this->setCode,
            'gameSlug' => $this->gameSlug,
            'pullOddsOneIn' => $this->pullOddsOneIn,
        ];
    }
}
