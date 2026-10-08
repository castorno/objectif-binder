<?php

declare(strict_types=1);

namespace App\Import\Exception;

/**
 * The import source cannot be read at all: missing file, unknown format.
 */
final class UnreadableSourceException extends \RuntimeException
{
}
