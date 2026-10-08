<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\OwnedCard;

/**
 * How much of a catalog search the user owns.
 */
final readonly class CollectionCompletionDto implements \JsonSerializable
{
    /**
     * @param int                               $total       cards matching the search
     * @param int                               $owned       those of them the user owns, in any language
     * @param array<string, list<OwnedCardDto>> $ownedOnPage what is owned of the cards of the requested page of the
     *                                                       search, by card id; cards not owned have no entry
     */
    public function __construct(
        public int $total,
        public int $owned,
        public array $ownedOnPage,
    ) {
    }

    /**
     * @param array<string, list<OwnedCard>> $ownedOnPage
     */
    public static function fromEntities(int $total, int $owned, array $ownedOnPage): self
    {
        return new self($total, $owned, array_map(
            static fn (array $ownedCards): array => array_map(OwnedCardDto::fromEntity(...), $ownedCards),
            $ownedOnPage,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'total' => $this->total,
            'owned' => $this->owned,
            // Always a JSON object, even empty: PHP would write [] otherwise.
            'ownedOnPage' => (object) $this->ownedOnPage,
        ];
    }
}
