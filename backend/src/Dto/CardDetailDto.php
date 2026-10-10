<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Card;
use App\Enum\CardFinish;

final readonly class CardDetailDto implements \JsonSerializable
{
    /**
     * @param array<string, mixed>  $attributes
     * @param list<string>|null     $finishes
     * @param list<CardIdentityDto> $identities
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
        public ?string $setReleaseDate,
        /** The set whose boosters hold the card: its own set, or the one that set comes with. */
        public string $mainSetName,
        public string $mainSetCode,
        public string $gameSlug,
        public ?string $imageUrl,
        public ?string $largeImageUrl,
        /** The finishes the card was printed with; null when unknown. */
        public ?array $finishes,
        public int|float|null $pullOddsOneIn,
        public ?string $pullOddsSource,
        public array $identities,
    ) {
    }

    public static function fromEntity(Card $card, int|float|null $pullOddsOneIn, ?string $pullOddsSource = null): self
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
            setReleaseDate: $set->getReleaseDate()?->format('Y-m-d'),
            mainSetName: $set->getMainSet()->getName(),
            mainSetCode: $set->getMainSet()->getCode(),
            gameSlug: $set->getGame()->getSlug(),
            imageUrl: $card->getImageUrl(),
            largeImageUrl: $card->getLargeImageUrl(),
            finishes: null === $card->getFinishes() ? null : array_map(static fn (CardFinish $finish): string => $finish->value, $card->getFinishes()),
            pullOddsOneIn: $pullOddsOneIn,
            pullOddsSource: $pullOddsSource,
            identities: array_map(CardIdentityDto::fromEntity(...), $card->getIdentities()->getValues()),
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
            'externalId' => $this->externalId,
            'attributes' => $this->attributes,
            'rarity' => $this->rarity,
            'setName' => $this->setName,
            'setCode' => $this->setCode,
            'setReleaseDate' => $this->setReleaseDate,
            'mainSetName' => $this->mainSetName,
            'mainSetCode' => $this->mainSetCode,
            'gameSlug' => $this->gameSlug,
            'imageUrl' => $this->imageUrl,
            'largeImageUrl' => $this->largeImageUrl,
            'finishes' => $this->finishes,
            'pullOddsOneIn' => $this->pullOddsOneIn,
            'pullOddsSource' => $this->pullOddsSource,
            'identities' => $this->identities,
        ];
    }
}
