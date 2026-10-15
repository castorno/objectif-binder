<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Dto\SetBoosterPriceRequest;
use App\Entity\CardSet;
use App\Exception\BoosterPriceOfSubSetException;
use App\Service\SetBoosterPriceService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The price of a booster of a set, entered by hand (see SetBoosterPriceService).
 */
final class SetBoosterPriceController
{
    public function __construct(
        private readonly SetBoosterPriceService $setBoosterPriceService,
    ) {
    }

    #[Route('/api/admin/sets/{id}/booster-price', name: 'admin_set_booster_price_put', methods: ['PUT'], format: 'json')]
    public function put(CardSet $cardSet, #[MapRequestPayload(acceptFormat: 'json')] SetBoosterPriceRequest $request): JsonResponse
    {
        try {
            // Validated as a whole number or nothing (see SetBoosterPriceRequest).
            $this->setBoosterPriceService->setPrice($cardSet, \is_int($request->priceCents) ? $request->priceCents : null);
        } catch (BoosterPriceOfSubSetException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return new JsonResponse(['boosterPriceCents' => $cardSet->getBoosterPriceCents()]);
    }
}
