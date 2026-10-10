<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Dto\SetForcedRarityRequest;
use App\Entity\CardSet;
use App\Exception\ForcedRarityNotAllowedException;
use App\Service\SetForcedRarityService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Names the rarity all the cards of a sub-set have, so that they can be
 * given a pull rate of their own in the boosters of their main set.
 */
final class SetForcedRarityController
{
    public function __construct(
        private readonly SetForcedRarityService $setForcedRarityService,
    ) {
    }

    #[Route('/api/admin/sets/{id}/forced-rarity', name: 'admin_set_forced_rarity_put', methods: ['PUT'], format: 'json')]
    public function put(CardSet $cardSet, #[MapRequestPayload(acceptFormat: 'json')] SetForcedRarityRequest $request): JsonResponse
    {
        try {
            $this->setForcedRarityService->force($cardSet, $request->name);
        } catch (ForcedRarityNotAllowedException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return new JsonResponse(['forcedRarity' => $cardSet->getForcedRarity()?->getName()]);
    }
}
