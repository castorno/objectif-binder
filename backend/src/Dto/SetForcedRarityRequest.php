<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of PUT /api/admin/sets/{id}/forced-rarity.
 */
final readonly class SetForcedRarityRequest
{
    public function __construct(
        /** Name of the rarity every card of the set gets; null or blank for none. */
        #[Assert\Length(max: 100, normalizer: 'trim')]
        public ?string $name = null,
    ) {
    }
}
