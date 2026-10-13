<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\OwnedCard;
use Symfony\Component\Validator\Constraints as Assert;

final class CardSearchQuery
{
    /** Value of `identity` asking for the cards that have none. */
    public const string WITHOUT_IDENTITY = 'none';

    /** Values of `sort`: sets in the order they came out, or the latest first. */
    public const string SORT_OLDEST = 'oldest';
    public const string SORT_NEWEST = 'newest';

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
        /** ISO 639-1 code: only the cards printed in that language, and those whose languages are unknown. */
        #[Assert\Regex(pattern: OwnedCard::LANGUAGE_PATTERN, message: 'Language must be an ISO 639-1 code.')]
        public readonly ?string $language = null,
        /** Not a filter: the order of the page, which counts and completion ignore. */
        #[Assert\Choice(choices: [self::SORT_OLDEST, self::SORT_NEWEST])]
        public readonly string $sort = self::SORT_OLDEST,
        #[Assert\Range(min: 1)]
        public readonly int $page = 1,
        #[Assert\Range(min: 1, max: 100)]
        public readonly int $limit = 20,
    ) {
    }
}
