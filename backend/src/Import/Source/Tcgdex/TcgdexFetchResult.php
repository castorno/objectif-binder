<?php

declare(strict_types=1);

namespace App\Import\Source\Tcgdex;

/**
 * What fetching one set did.
 */
final readonly class TcgdexFetchResult
{
    public function __construct(
        public string $setId,
        public TcgdexFetchStatus $status,
        /** The file that holds the set, ready for app:import, once downloaded. */
        public string $path,
        public int $cardCount = 0,
        /** How many cards TCGdex lists for the set in the catalog's language; null when it does not say. */
        public ?int $expectedCardCount = null,
    ) {
    }

    public function isComplete(): bool
    {
        return null === $this->expectedCardCount || $this->cardCount === $this->expectedCardCount;
    }
}
