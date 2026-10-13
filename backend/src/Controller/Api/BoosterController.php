<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\OwnedCard;
use App\Entity\User;
use App\Repository\CardSetRepository;
use App\Service\BoosterOddsService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Which booster to open, for the signed-in user: the answer depends on
 * what they own, so it sits with the routes of the collection.
 */
final class BoosterController
{
    public function __construct(
        private readonly BoosterOddsService $boosterOddsService,
        private readonly CardSetRepository $cardSetRepository,
    ) {
    }

    /**
     * The sets whose pull rates are entered, with what a booster of each
     * can be expected to bring, the most rewarding first.
     */
    #[Route('/api/collection/boosters', name: 'collection_boosters', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $user,
        #[MapQueryParameter] ?string $game = null,
        // A booster of that language: only the cards printed in it are counted.
        #[MapQueryParameter(filter: \FILTER_VALIDATE_REGEXP, options: ['regexp' => OwnedCard::LANGUAGE_PATTERN], validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ?string $language = null,
    ): JsonResponse {
        $game = null === $game || '' === $game ? null : $game;

        return new JsonResponse([
            'data' => $this->boosterOddsService->boostersFor($user, $game, $language),
            // Sets nothing can be said about yet: their pull rates are not entered.
            'setsWithoutRates' => $this->cardSetRepository->countMainSetsWithoutPullRates($game),
        ]);
    }
}
