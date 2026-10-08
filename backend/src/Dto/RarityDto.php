<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Rarity;

final readonly class RarityDto implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public int $sortOrder,
    ) {
    }

    public static function fromEntity(Rarity $rarity): self
    {
        return new self(
            id: (string) $rarity->getId(),
            name: $rarity->getName(),
            sortOrder: $rarity->getSortOrder(),
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
        ];
    }
}
