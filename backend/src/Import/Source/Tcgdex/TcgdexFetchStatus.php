<?php

declare(strict_types=1);

namespace App\Import\Source\Tcgdex;

enum TcgdexFetchStatus
{
    case Downloaded;
    /** The file was already there and was left as it is. */
    case AlreadyDownloaded;
    /** The set has no card in the catalog's language: there is nothing to download. */
    case Empty;
    /** The set is not made of physical cards: see TcgdexFetcher::DIGITAL_SERIES. */
    case Digital;
}
