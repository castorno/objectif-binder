<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * How much of a catalog search the user owns.
 */
final readonly class CollectionCompletionDto implements \JsonSerializable
{
    /**
     * @param int          $total        cards matching the search
     * @param int          $owned        those of them the user owns, in any language
     * @param list<string> $ownedCardIds the owned cards among the requested page of the search
     */
    public function __construct(
        public int $total,
        public int $owned,
        public array $ownedCardIds,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'total' => $this->total,
            'owned' => $this->owned,
            'ownedCardIds' => $this->ownedCardIds,
        ];
    }
}
