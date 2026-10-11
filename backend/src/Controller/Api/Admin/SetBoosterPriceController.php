<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Dto\SetBoosterPriceRequest;
use App\Entity\CardSet;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The price of a booster of a set, entered by hand: it is what lets the
 * boosters be compared for what they bring per euro.
 */
final class SetBoosterPriceController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/admin/sets/{id}/booster-price', name: 'admin_set_booster_price_put', methods: ['PUT'], format: 'json')]
    public function put(CardSet $cardSet, #[MapRequestPayload(acceptFormat: 'json')] SetBoosterPriceRequest $request): JsonResponse
    {
        // Validated as a whole number or nothing (see SetBoosterPriceRequest).
        $priceCents = \is_int($request->priceCents) ? $request->priceCents : null;

        if (null !== $priceCents && null !== $cardSet->getParent()) {
            throw new UnprocessableEntityHttpException(sprintf('The cards of this set come in the boosters of "%s": the price is entered there.', $cardSet->getParent()->getName()));
        }

        $cardSet->setBoosterPriceCents($priceCents);
        $this->entityManager->flush();

        return new JsonResponse(['boosterPriceCents' => $cardSet->getBoosterPriceCents()]);
    }
}
