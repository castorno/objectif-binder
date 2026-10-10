<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * A rarity was forced on the cards of a set that stands on its own.
 */
final class ForcedRarityNotAllowedException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Only a sub-set can give all its cards one rarity: link it to its main set first.');
    }
}
