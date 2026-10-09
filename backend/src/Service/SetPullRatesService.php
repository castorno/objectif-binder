<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CardSet;
use App\Entity\PullRate;
use App\Entity\Rarity;
use App\Exception\UnknownRarityException;
use App\Repository\CardRepository;
use App\Repository\PullRateRepository;
use App\Repository\RarityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * The pull rates of a set, as an administrator reads and enters them: one
 * figure per rarity, all at once.
 */
final class SetPullRatesService
{
    public function __construct(
        private readonly PullRateRepository $pullRateRepository,
        private readonly RarityRepository $rarityRepository,
        private readonly CardRepository $cardRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * The rarities a pull rate makes sense for in this set: those of its
     * cards, and those that already have one (a rate entered before its
     * cards were removed must stay within reach, to be deleted).
     *
     * @return list<array{rarity: Rarity, cardsInSet: int, pullRate: ?PullRate}> in the order of the rarities
     */
    public function ratesOf(CardSet $cardSet): array
    {
        $cardCounts = $this->cardRepository->countByRarityInCardSet($cardSet);

        $pullRates = [];
        foreach ($this->pullRateRepository->findByCardSet($cardSet) as $pullRate) {
            $pullRates[(string) $pullRate->getRarity()->getId()] = $pullRate;
        }

        $rows = [];
        foreach ($this->rarityRepository->findBy(['game' => $cardSet->getGame()], ['sortOrder' => 'ASC', 'name' => 'ASC']) as $rarity) {
            $id = (string) $rarity->getId();
            if (isset($cardCounts[$id]) || isset($pullRates[$id])) {
                $rows[] = ['rarity' => $rarity, 'cardsInSet' => $cardCounts[$id] ?? 0, 'pullRate' => $pullRates[$id] ?? null];
            }
        }

        return $rows;
    }

    /**
     * Makes the pull rates of the set exactly those given: creates, changes
     * and deletes what it takes.
     *
     * @param array<string, array{cards: int, boosters: int}> $ratesByRarityId so many cards for so many boosters, by rarity id
     *
     * @throws UnknownRarityException when an id is not a rarity of the set's game
     */
    public function replace(CardSet $cardSet, array $ratesByRarityId, ?string $source): void
    {
        $rarities = [];
        foreach ($this->rarityRepository->findBy(['game' => $cardSet->getGame()]) as $rarity) {
            $rarities[(string) $rarity->getId()] = $rarity;
        }
        foreach (array_keys($ratesByRarityId) as $rarityId) {
            if (!isset($rarities[$rarityId])) {
                throw new UnknownRarityException((string) $rarityId);
            }
        }

        $now = $this->clock->now();
        $source = null === $source || '' === trim($source) ? null : trim($source);

        foreach ($this->pullRateRepository->findByCardSet($cardSet) as $pullRate) {
            $rarityId = (string) $pullRate->getRarity()->getId();

            if (isset($ratesByRarityId[$rarityId])) {
                $pullRate->update($ratesByRarityId[$rarityId]['cards'], $ratesByRarityId[$rarityId]['boosters'], $source, $now);
                unset($ratesByRarityId[$rarityId]);
            } else {
                $this->entityManager->remove($pullRate);
            }
        }

        foreach ($ratesByRarityId as $rarityId => $rate) {
            $this->entityManager->persist(new PullRate($cardSet, $rarities[$rarityId], $rate['cards'], $rate['boosters'], $source, $now));
        }

        $this->entityManager->flush();
    }
}
