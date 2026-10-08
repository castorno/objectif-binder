<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class IdentitySearchQuery
{
    public function __construct(
        #[Assert\Length(max: 100)]
        public readonly ?string $q = null,
        public readonly ?string $game = null,
        #[Assert\Range(min: 1)]
        public readonly int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public readonly int $limit = 20,
    ) {
    }

    /**
     * The search for the cards these identities leave out: those of the same
     * game that have none.
     */
    public function cardsWithoutIdentity(): CardSearchQuery
    {
        return new CardSearchQuery(game: $this->game, identity: CardSearchQuery::WITHOUT_IDENTITY);
    }
}
