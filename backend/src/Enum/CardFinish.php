<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * A surface a card was printed with. The same card, under the same number of
 * the same set, often exists in several: a plain one and a shiny one.
 */
enum CardFinish: string
{
    case Normal = 'normal';
    /** The picture shines. */
    case Holo = 'holo';
    /** Everything but the picture shines. */
    case Reverse = 'reverse';
}
