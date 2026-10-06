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
    ) {
    }

    public static function fromEntity(CardSet $set): self
    {
        return new self(
            id: (string) $set->getId(),
            name: $set->getName(),
            code: $set->getCode(),
            releaseDate: $set->getReleaseDate()?->format('Y-m-d'),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'releaseDate' => $this->releaseDate,
        ];
    }
}
