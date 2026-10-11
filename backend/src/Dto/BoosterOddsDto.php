<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\CardSet;

/**
 * What a user can expect from a booster of a set, given what they own.
 */
final readonly class BoosterOddsDto implements \JsonSerializable
{
    /**
     * @param list<array{id: string, name: string, chance: float}> $likeliest
     */
    public function __construct(
        public CardSet $set,
        /** How many identities the user owns nothing of a booster gives on average. */
        public float $expectedNewIdentities,
        /** How many cards the user does not own a booster gives on average. */
        public float $expectedMissingCards,
        /** How many cards a booster holds on average, among the rarities that have a pull rate. */
        public float $cardsPerBooster,
        public int $identityCount,
        public int $missingIdentityCount,
        public int $cardCount,
        public int $missingCardCount,
        /** Some cards of the boosters have a rarity without pull rate: the figures only cover the others. */
        public bool $incomplete,
        /** The identities the user owns nothing of that a booster is most likely to give, most likely first. */
        public array $likeliest,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $game = $this->set->getGame();

        return [
            'set' => [
                'id' => (string) $this->set->getId(),
                'name' => $this->set->getName(),
                'code' => $this->set->getCode(),
                'releaseDate' => $this->set->getReleaseDate()?->format('Y-m-d'),
                'gameSlug' => $game->getSlug(),
                // What the game calls its index of identities, when it says.
                'identityLabel' => $game->getIdentityLabel(),
            ],
            'expectedNewIdentities' => round($this->expectedNewIdentities, 4),
            'expectedMissingCards' => round($this->expectedMissingCards, 4),
            'cardsPerBooster' => round($this->cardsPerBooster, 4),
            'identityCount' => $this->identityCount,
            'missingIdentityCount' => $this->missingIdentityCount,
            'cardCount' => $this->cardCount,
            'missingCardCount' => $this->missingCardCount,
            'incomplete' => $this->incomplete,
            'likeliest' => array_map(
                static fn (array $identity): array => ['id' => $identity['id'], 'name' => $identity['name'], 'chance' => round($identity['chance'], 4)],
                $this->likeliest,
            ),
        ];
    }
}
