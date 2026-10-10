<?php

declare(strict_types=1);

namespace App\Exception;

use App\Enum\CardFinish;

/**
 * A copy of a card was given a finish the card was never printed with.
 */
final class FinishNotAvailableException extends \DomainException
{
    public function __construct(CardFinish $finish)
    {
        parent::__construct(sprintf('This card does not exist in the "%s" finish.', $finish->value));
    }
}
