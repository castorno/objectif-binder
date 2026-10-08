<?php

declare(strict_types=1);

namespace App\Import;

/**
 * What importing one card did to the catalog.
 */
enum ImportOutcome
{
    case Created;
    case Updated;
    case Unchanged;
}
