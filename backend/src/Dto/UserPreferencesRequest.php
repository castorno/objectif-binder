<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\CardCondition;

/**
 * Body of PUT /api/me/preferences. As with any PUT, the body is the whole
 * new state: leaving a preference out clears it.
 */
final readonly class UserPreferencesRequest
{
    public function __construct(
        public ?CardCondition $defaultCondition = null,
    ) {
    }
}
