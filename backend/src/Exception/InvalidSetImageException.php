<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * A file sent as the picture of a set is not one this application keeps.
 */
final class InvalidSetImageException extends \DomainException
{
}
