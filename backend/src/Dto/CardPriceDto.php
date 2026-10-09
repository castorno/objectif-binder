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
        public ?string $productUrl,
        /**
         * Other cards of the set have the same name: a source matching cards to
         * marketplace products by name may have given this one the price of another.
         */
        public bool $sharesNameInSet,
    ) {
    }

    /**
     * @param bool $withShinyAmounts false for a card with no shiny version of its own: a
     *                               marketplace still reports amounts for one, from the
     *                               listings sellers filed under the wrong finish
     */
    public static function fromEntity(CardPrice $price, bool $sharesNameInSet, bool $withShinyAmounts = true): self
    {
        return new self(
            marketplace: $price->getMarketplace(),
            currency: $price->getCurrency(),
            trendCents: $price->getTrendCents(),
            lowCents: $price->getLowCents(),
            average30DaysCents: $price->getAverage30DaysCents(),
            holoTrendCents: $withShinyAmounts ? $price->getHoloTrendCents() : null,
            holoLowCents: $withShinyAmounts ? $price->getHoloLowCents() : null,
            holoAverage30DaysCents: $withShinyAmounts ? $price->getHoloAverage30DaysCents() : null,
            sourceUpdatedAt: $price->getSourceUpdatedAt()?->format(\DATE_ATOM),
            fetchedAt: $price->getFetchedAt()->format(\DATE_ATOM),
            productUrl: $price->getProductUrl(),
            sharesNameInSet: $sharesNameInSet,
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
