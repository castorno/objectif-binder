<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CardSearchQuery
{
    /** Value of `identity` asking for the cards that have none. */
    public const string WITHOUT_IDENTITY = 'none';

    public function __construct(
        #[Assert\Length(max: 100)]
        public readonly ?string $q = null,
        public readonly ?string $game = null,
        public readonly ?string $set = null,
        public readonly ?string $rarity = null,
        /** Id of a card identity, or WITHOUT_IDENTITY. */
        #[Assert\AtLeastOneOf(
            [new Assert\Uuid(), new Assert\IdenticalTo(self::WITHOUT_IDENTITY)],
            message: 'This value should be the id of an identity, or "none".',
            includeInternalMessages: false,
        )]
        public readonly ?string $identity = null,
        #[Assert\Range(min: 1)]
        public readonly int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public readonly int $limit = 20,
    ) {
    }
}
