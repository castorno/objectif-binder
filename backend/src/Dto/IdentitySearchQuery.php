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
        /** Name of a group of identities: see CardIdentity::$groupName. */
        #[Assert\Length(max: 100)]
        public readonly ?string $group = null,
        #[Assert\Range(min: 1)]
        public readonly int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public readonly int $limit = 20,
    ) {
    }

    /**
     * The search for the cards these identities leave out: those of the same
     * game that have none. Null when the search is narrowed to a group of
     * identities: cards without identity belong to no group.
     */
    public function cardsWithoutIdentity(): ?CardSearchQuery
    {
        if (null !== $this->group && '' !== $this->group) {
            return null;
        }

        return new CardSearchQuery(game: $this->game, identity: CardSearchQuery::WITHOUT_IDENTITY);
    }
}
