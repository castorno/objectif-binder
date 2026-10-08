<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\CardPrice;

/**
 * The estimated price of a card. Amounts are in cents of `currency`; any of
 * them can be null.
 */
final readonly class CardPriceDto implements \JsonSerializable
{
    public function __construct(
        public ?string $marketplace,
        public ?string $currency,
        public ?int $trendCents,
        public ?int $lowCents,
        public ?int $average30DaysCents,
        public ?int $holoTrendCents,
        public ?int $holoLowCents,
        public ?int $holoAverage30DaysCents,
        public ?string $sourceUpdatedAt,
        public string $fetchedAt,
    ) {
    }

    public static function fromEntity(CardPrice $price): self
    {
        return new self(
            marketplace: $price->getMarketplace(),
            currency: $price->getCurrency(),
            trendCents: $price->getTrendCents(),
            lowCents: $price->getLowCents(),
            average30DaysCents: $price->getAverage30DaysCents(),
            holoTrendCents: $price->getHoloTrendCents(),
            holoLowCents: $price->getHoloLowCents(),
            holoAverage30DaysCents: $price->getHoloAverage30DaysCents(),
            sourceUpdatedAt: $price->getSourceUpdatedAt()?->format(\DATE_ATOM),
            fetchedAt: $price->getFetchedAt()->format(\DATE_ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
