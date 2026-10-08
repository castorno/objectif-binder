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
        /** The file that holds the set, ready for app:import. */
        public string $path,
        /** False when the file was already there and was left as it is. */
        public bool $downloaded,
        public int $cardCount = 0,
        /** How many cards TCGdex says the set has; null when it does not say. */
        public ?int $expectedCardCount = null,
    ) {
    }

    public function isComplete(): bool
    {
        return null === $this->expectedCardCount || $this->cardCount === $this->expectedCardCount;
    }
}
