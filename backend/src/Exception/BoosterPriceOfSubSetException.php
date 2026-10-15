<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * A booster price was given to a set whose cards come in the boosters of another.
 */
final class BoosterPriceOfSubSetException extends \DomainException
{
    public function __construct(string $mainSetName)
    {
        parent::__construct(sprintf('The cards of this set come in the boosters of "%s": the price is entered there.', $mainSetName));
    }
}
