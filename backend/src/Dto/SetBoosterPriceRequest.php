<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of PUT /api/admin/sets/{id}/booster-price.
 */
final readonly class SetBoosterPriceRequest
{
    public function __construct(
        /**
         * In euro cents; null for a price that is not known. Not typed as a
         * whole number: PHP would quietly turn 5.99, sent by someone
         * thinking in euros, into 5 cents. Checked instead, and refused.
         */
        #[Assert\Sequentially([
            new Assert\Type('int'),
            new Assert\Range(min: 1, max: 100_000_00),
        ])]
        public mixed $priceCents = null,
    ) {
    }
}
