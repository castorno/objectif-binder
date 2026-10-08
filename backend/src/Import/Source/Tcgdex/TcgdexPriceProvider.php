<?php

declare(strict_types=1);

namespace App\Import\Source\Tcgdex;

use App\Entity\Card;
use App\Pricing\CardPriceProvider;
use App\Pricing\PriceQuote;
use App\Pricing\PriceUnavailableException;

/**
 * Prices of the cards imported from TCGdex, which relays those of
 * Cardmarket, the main European marketplace, in euros.
 *
 * A Cardmarket figure covers every copy of a card on sale, whatever its
 * language and condition: an order of magnitude, not a quote.
 */
final class TcgdexPriceProvider implements CardPriceProvider
{
    private const string MARKETPLACE = 'Cardmarket';

    public function __construct(
        private readonly TcgdexClient $client,
    ) {
    }

    public function supports(Card $card): bool
    {
        // Cards of this game got their external id from TCGdex: see TcgdexCardMapper.
        return null !== $card->getExternalId() && TcgdexCardMapper::GAME_SLUG === $card->getCardSet()->getGame()->getSlug();
    }

    public function fetch(Card $card): ?PriceQuote
    {
        try {
            $details = $this->client->fetchCard((string) $card->getExternalId());
        } catch (TcgdexException $exception) {
            throw new PriceUnavailableException($exception->getMessage(), previous: $exception);
        }

        $market = $details['pricing']['cardmarket'] ?? null;
        if (!\is_array($market)) {
            return null;
        }

        $updated = \is_string($market['updated'] ?? null) ? date_create_immutable($market['updated']) : false;

        return new PriceQuote(
            marketplace: self::MARKETPLACE,
            currency: \is_string($market['unit'] ?? null) ? strtoupper($market['unit']) : 'EUR',
            trendCents: $this->cents($market['trend'] ?? null),
            lowCents: $this->cents($market['low'] ?? null),
            average30DaysCents: $this->cents($market['avg30'] ?? null),
            holoTrendCents: $this->cents($market['trend-holo'] ?? null),
            holoLowCents: $this->cents($market['low-holo'] ?? null),
            holoAverage30DaysCents: $this->cents($market['avg30-holo'] ?? null),
            sourceUpdatedAt: false === $updated ? null : $updated,
            productUrl: $this->productUrl($market['idProduct'] ?? null),
        );
    }

    /**
     * Where Cardmarket shows the product TCGdex matched the card to.
     */
    private function productUrl(mixed $productId): ?string
    {
        return \is_int($productId) && $productId > 0
            ? 'https://www.cardmarket.com/fr/Pokemon/Products?idProduct='.$productId
            : null;
    }

    /**
     * A price as TCGdex writes it (0.07) in cents (7). Zero means "none on
     * sale", not "free".
     */
    private function cents(mixed $amount): ?int
    {
        if (!\is_int($amount) && !\is_float($amount)) {
            return null;
        }

        $cents = (int) round($amount * 100);

        return $cents > 0 ? $cents : null;
    }
}
