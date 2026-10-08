<?php

declare(strict_types=1);

namespace App\Pricing;

/**
 * What a marketplace says a card sells for, as a price source reports it.
 * Amounts are in cents, never in decimals: a price is counted, not measured,
 * and floating-point numbers cannot hold 0.10 exactly.
 */
final readonly class PriceQuote
{
    public function __construct(
        /** The marketplace the figures come from, as shown to users. */
        public string $marketplace,
        /** ISO 4217 code, e.g. "EUR". */
        public string $currency,
        /** What the card has been selling for lately. */
        public ?int $trendCents,
        /** The cheapest copy on sale. */
        public ?int $lowCents,
        public ?int $average30DaysCents,
        /** The same three figures for the holographic version, when the marketplace tells them apart. */
        public ?int $holoTrendCents,
        public ?int $holoLowCents,
        public ?int $holoAverage30DaysCents,
        /** When the marketplace figures were last refreshed by the source. */
        public ?\DateTimeImmutable $sourceUpdatedAt,
        /** The page of the marketplace the figures are about, for a person to check what they stand for. */
        public ?string $productUrl = null,
    ) {
    }
}
