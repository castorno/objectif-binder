<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Pull rates were given to a set whose cards come in the boosters of another.
 */
final class PullRatesOfSubSetException extends \DomainException
{
    public function __construct(string $mainSetName)
    {
        parent::__construct(sprintf('The cards of this set come in the boosters of "%s": its pull rates are entered there.', $mainSetName));
    }
}
