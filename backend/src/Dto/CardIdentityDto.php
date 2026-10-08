<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\CardIdentity;

final readonly class CardIdentityDto implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
        public ?int $sortOrder,
    ) {
    }

    public static function fromEntity(CardIdentity $identity): self
    {
        return new self(
            id: (string) $identity->getId(),
            name: $identity->getName(),
            sortOrder: $identity->getSortOrder(),
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
