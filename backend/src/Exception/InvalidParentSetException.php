<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * A set was given a parent it cannot have.
 */
final class InvalidParentSetException extends \DomainException
{
}
