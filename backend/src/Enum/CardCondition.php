<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Physical state of an owned card, on the seven-step scale used by the main
 * European marketplace, from best to worst. The same scale applies to every
 * game, which is why this is an enum and not a per-game table like Rarity.
 */
enum CardCondition: string
{
    case Mint = 'mint';
    case NearMint = 'near_mint';
    case Excellent = 'excellent';
    case Good = 'good';
    case LightPlayed = 'light_played';
    case Played = 'played';
    case Poor = 'poor';
}
