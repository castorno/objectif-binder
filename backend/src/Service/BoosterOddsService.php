<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\BoosterOddsDto;
use App\Entity\CardSet;
use App\Entity\User;
use App\Image\SetImages;
use App\Repository\BoosterContentRepository;
use App\Repository\PullRateRepository;

/**
 * Which booster to open: for each set whose pull rates are entered, what a
 * booster can be expected to bring to a user, given what they own.
 *
 * Gathers the rates, what the boosters hold and what the user has; the
 * arithmetic itself is BoosterOddsCalculator's. Four queries whatever the
 * number of sets.
 */
final class BoosterOddsService
{
    /** How many of the likeliest new identities are named for each booster. */
    private const int LIKELIEST = 5;

    public function __construct(
        private readonly PullRateRepository $pullRateRepository,
        private readonly BoosterContentRepository $boosterContentRepository,
        private readonly BoosterOddsCalculator $calculator,
        private readonly SetImages $setImages,
    ) {
    }

    /**
     * @param string|null $gameSlug null for every game
     *
     * @return list<BoosterOddsDto> the booster bringing the most new identities first
     */
    public function boostersFor(User $user, ?string $gameSlug = null): array
    {
        /** @var array<string, CardSet> $sets */
        $sets = [];
        /** @var array<string, array<string, array{cards: int, boosters: int}>> $rates by set id, then by rarity id */
        $rates = [];
        foreach ($this->pullRateRepository->findOfMainSets($gameSlug) as $pullRate) {
            $setId = (string) $pullRate->getCardSet()->getId();
            $sets[$setId] = $pullRate->getCardSet();
            $rates[$setId][(string) $pullRate->getRarity()->getId()] = ['cards' => $pullRate->getCardCount(), 'boosters' => $pullRate->getBoosterCount()];
        }

        $mainSets = array_values($sets);
        $cardsByRarity = $this->boosterContentRepository->countCardsByRarity($mainSets, $user);
        $cardsByIdentity = $this->boosterContentRepository->countCardsByIdentityAndRarity($mainSets);
        $ownedIdentities = [] === $mainSets ? [] : $this->boosterContentRepository->findOwnedIdentityIds($user);

        $boosters = [];
        foreach ($sets as $setId => $set) {
            $boosters[] = $this->booster($set, $rates[$setId], $cardsByRarity[$setId] ?? [], $cardsByIdentity[$setId] ?? [], $ownedIdentities);
        }

        usort($boosters, static fn (BoosterOddsDto $a, BoosterOddsDto $b): int => [$b->expectedNewIdentities, $b->expectedMissingCards, $a->set->getName()]
            <=> [$a->expectedNewIdentities, $a->expectedMissingCards, $b->set->getName()]);

        return $boosters;
    }

    /**
     * @param array<string, array{cards: int, boosters: int}>                    $rates           by rarity id
     * @param array<string, array{total: int, missing: int}>                     $cardsByRarity   by rarity id
     * @param array<string, array{name: string, cards: array<string, int>}>      $cardsByIdentity by identity id
     * @param array<string, true>                                                $ownedIdentities by identity id
     */
    private function booster(CardSet $set, array $rates, array $cardsByRarity, array $cardsByIdentity, array $ownedIdentities): BoosterOddsDto
    {
        $cardCount = $missingCardCount = 0;
        $expectedMissingCards = $cardsPerBooster = 0.0;
        $incomplete = false;

        foreach ($cardsByRarity as $rarityId => $cards) {
            $cardCount += $cards['total'];
            $missingCardCount += $cards['missing'];

            $rate = $rates[$rarityId] ?? null;
            if (null === $rate) {
                // Cards a booster may well hold, and nothing says how often.
                $incomplete = true;
                continue;
            }

            $expectedMissingCards += $this->calculator->expectedCards($rate['cards'], $rate['boosters'], $cards['total'], $cards['missing']);
            $cardsPerBooster += $this->calculator->expectedCards($rate['cards'], $rate['boosters'], $cards['total'], $cards['total']);
        }

        $expectedNewIdentities = 0.0;
        $missingIdentityCount = 0;
        $new = [];
        foreach ($cardsByIdentity as $identityId => $identity) {
            if (isset($ownedIdentities[$identityId])) {
                continue;
            }
            ++$missingIdentityCount;

            // An identity printed in several rarities can come from any of them.
            $chances = [];
            foreach ($identity['cards'] as $rarityId => $cards) {
                if (isset($rates[$rarityId], $cardsByRarity[$rarityId])) {
                    $chances[] = $this->calculator->chanceOfAny($rates[$rarityId]['cards'], $rates[$rarityId]['boosters'], $cardsByRarity[$rarityId]['total'], $cards);
                }
            }
            $chance = $this->calculator->chanceOfAnyAmong($chances);

            // The average number of new identities is the sum of the chances
            // of each: true whether or not they depend on one another.
            $expectedNewIdentities += $chance;
            $new[] = ['id' => $identityId, 'name' => $identity['name'], 'chance' => $chance];
        }

        usort($new, static fn (array $a, array $b): int => [$b['chance'], $a['name']] <=> [$a['chance'], $b['name']]);

        return new BoosterOddsDto(
            set: $set,
            imageUrl: $this->setImages->addressOf($set),
            expectedNewIdentities: $expectedNewIdentities,
            expectedMissingCards: $expectedMissingCards,
            cardsPerBooster: $cardsPerBooster,
            identityCount: \count($cardsByIdentity),
            missingIdentityCount: $missingIdentityCount,
            cardCount: $cardCount,
            missingCardCount: $missingCardCount,
            incomplete: $incomplete,
            likeliest: \array_slice($new, 0, self::LIKELIEST),
        );
    }
}
