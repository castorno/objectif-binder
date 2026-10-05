<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Game;

final readonly class GameDto implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
    ) {
    }

    public static function fromEntity(Game $game): self
    {
        return new self(
            id: (string) $game->getId(),
            name: $game->getName(),
            slug: $game->getSlug(),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}
