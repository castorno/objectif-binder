<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\CardSet;
use App\Entity\Game;
use App\Repository\CardSetRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * What is already entered for each set of a game with boosters of its own,
 * so that an administrator sees which are left to do. Sub-sets are left
 * out: they have neither price nor rates of their own.
 */
final class SetProgressController
{
    public function __construct(
        private readonly CardSetRepository $cardSetRepository,
    ) {
    }

    #[Route('/api/admin/games/{slug}/set-progress', name: 'admin_set_progress', methods: ['GET'])]
    public function list(#[MapEntity(mapping: ['slug' => 'slug'])] Game $game): JsonResponse
    {
        // One query for all the sets, not one per set.
        $complete = array_flip($this->cardSetRepository->findIdsOfSetsWithCompletePullRates($game));
        $sets = $this->cardSetRepository->findBy(['game' => $game, 'parent' => null]);

        return new JsonResponse(array_map(static fn (CardSet $set): array => [
            'setId' => (string) $set->getId(),
            'hasBoosterPrice' => null !== $set->getBoosterPriceCents(),
            'hasCompletePullRates' => isset($complete[(string) $set->getId()]),
        ], $sets));
    }
}
