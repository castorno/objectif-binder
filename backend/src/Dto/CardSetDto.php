<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\CardSet;

final readonly class CardSetDto implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public string $code,
        public ?string $releaseDate,
        /** Code of the set this one comes in the boosters of, if any. */
        public ?string $parentCode,
        /** Whether at least one card of the set has a picture. */
        public bool $hasPictures,
    ) {
    }

    public static function fromEntity(CardSet $set, bool $hasPictures): self
    {
        return new self(
            id: (string) $set->getId(),
            name: $set->getName(),
            code: $set->getCode(),
            releaseDate: $set->getReleaseDate()?->format('Y-m-d'),
            parentCode: $set->getParent()?->getCode(),
            hasPictures: $hasPictures,
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
            'code' => $this->code,
            'releaseDate' => $this->releaseDate,
            'parentCode' => $this->parentCode,
            'hasPictures' => $this->hasPictures,
        ];
    }
}
