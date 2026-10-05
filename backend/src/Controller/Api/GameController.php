<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\GameDto;
use App\Repository\GameRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GameController
{
    public function __construct(
        private readonly GameRepository $gameRepository,
    ) {
    }

    #[Route('/api/games', name: 'game_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $games = $this->gameRepository->findBy([], ['name' => 'ASC']);

        return new JsonResponse(array_map(GameDto::fromEntity(...), $games));
    }
}
