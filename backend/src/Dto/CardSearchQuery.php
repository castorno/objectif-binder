<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CardSearchQuery
{
    public function __construct(
        #[Assert\Length(max: 100)]
        public readonly ?string $q = null,
        public readonly ?string $game = null,
        public readonly ?string $set = null,
        public readonly ?string $rarity = null,
        #[Assert\Range(min: 1)]
        public readonly int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public readonly int $limit = 20,
    ) {
    }
}
