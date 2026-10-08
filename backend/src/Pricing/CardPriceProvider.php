<?php

declare(strict_types=1);

namespace App\Pricing;

use App\Entity\Card;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Somewhere prices of cards can be asked for. Which games a source knows,
 * and how it names their cards, stays behind this interface.
 */
#[AutoconfigureTag('app.card_price_provider')]
interface CardPriceProvider
{
    public function supports(Card $card): bool;

    /**
     * @return PriceQuote|null null when the source knows the card but has no price for it
     *
     * @throws PriceUnavailableException when the source could not be asked
     */
    public function fetch(Card $card): ?PriceQuote;
}
