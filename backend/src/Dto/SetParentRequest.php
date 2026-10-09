<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of PUT /api/admin/sets/{id}/parent.
 */
final readonly class SetParentRequest
{
    public function __construct(
        /** Id of the set this one comes in the boosters of; null for none. */
        #[Assert\Uuid]
        public ?string $parentId = null,
    ) {
    }
}
