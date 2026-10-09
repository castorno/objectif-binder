<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * A pull rate was given for something that is not a rarity of the game.
 */
final class UnknownRarityException extends \DomainException
{
    public function __construct(string $rarityId)
    {
        parent::__construct(sprintf('"%s" is not a rarity of this game.', $rarityId));
    }
}
