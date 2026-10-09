<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of PUT /api/admin/sets/{id}/pull-rates. The set comes from the URL.
 *
 * As with any PUT, the body is the whole new state: a rarity left out of
 * `rates` loses its pull rate.
 */
final readonly class SetPullRatesRequest
{
    /**
     * @param array<string, array{cards: int, boosters: int}> $rates  by rarity id: so many cards for so many boosters
     * @param string|null                                     $source where the figures come from, for all of them
     */
    public function __construct(
        #[Assert\All([
            new Assert\Collection(fields: [
                'cards' => [new Assert\Type('int'), new Assert\Range(min: 1, max: 1000)],
                'boosters' => [new Assert\Type('int'), new Assert\Range(min: 1, max: 1_000_000)],
            ]),
        ])]
        public array $rates = [],
        #[Assert\Length(max: 255)]
        public ?string $source = null,
    ) {
    }
}
