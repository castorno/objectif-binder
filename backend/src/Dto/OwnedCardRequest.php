<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\CardCondition;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of PUT /api/collection/cards/{id}/{language}. The card and the language
 * come from the URL and the owner from the access token: none of them can be
 * set here.
 *
 * As with any PUT, the body is the whole new state: leaving the condition out
 * clears it.
 */
final readonly class OwnedCardRequest
{
    public function __construct(
        // Removing a card is a DELETE, not a quantity of zero.
        #[Assert\Range(min: 1, max: 9999)]
        public int $quantity,
        public ?CardCondition $condition = null,
    ) {
    }
}
