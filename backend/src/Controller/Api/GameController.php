<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\CardSetDto;
use App\Dto\GameDto;
use App\Dto\RarityDto;
use App\Entity\Game;
use App\Repository\CardIdentityRepository;
use App\Repository\CardSetRepository;
use App\Repository\GameRepository;
use App\Repository\RarityRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GameController
{
    public function __construct(
        private readonly GameRepository $gameRepository,
        private readonly CardSetRepository $cardSetRepository,
        private readonly RarityRepository $rarityRepository,
        private readonly CardIdentityRepository $cardIdentityRepository,
    ) {
    }

    #[Route('/api/games', name: 'game_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $games = $this->gameRepository->findBy([], ['name' => 'ASC']);

        return new JsonResponse(array_map(GameDto::fromEntity(...), $games));
    }

    #[Route('/api/games/{slug}/sets', name: 'game_sets', methods: ['GET'])]
    public function sets(#[MapEntity(mapping: ['slug' => 'slug'])] Game $game): JsonResponse
    {
        $sets = $this->cardSetRepository->findBy(['game' => $game], ['releaseDate' => 'DESC', 'name' => 'ASC']);

        return new JsonResponse(array_map(CardSetDto::fromEntity(...), $sets));
    }

    #[Route('/api/games/{slug}/rarities', name: 'game_rarities', methods: ['GET'])]
    public function rarities(#[MapEntity(mapping: ['slug' => 'slug'])] Game $game): JsonResponse
    {
        $rarities = $this->rarityRepository->findBy(['game' => $game], ['sortOrder' => 'ASC', 'name' => 'ASC']);

        return new JsonResponse(array_map(RarityDto::fromEntity(...), $rarities));
    }

    /**
     * The groups the identities of the game are sorted into, for the filter
     * of the grouped catalog. Empty for a game that has none.
     */
    #[Route('/api/games/{slug}/identity-groups', name: 'game_identity_groups', methods: ['GET'])]
    public function identityGroups(#[MapEntity(mapping: ['slug' => 'slug'])] Game $game): JsonResponse
    {
        return new JsonResponse($this->cardIdentityRepository->findGroupsByGame($game));
    }
}
