<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * A file given as the picture of a card is not one this application keeps.
 */
final class InvalidCardImageException extends \DomainException
{
}
