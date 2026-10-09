<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\CardDetailDto;
use App\Dto\CardPriceDto;
use App\Dto\CardSearchQuery;
use App\Dto\CardSummaryDto;
use App\Entity\Card;
use App\Pricing\CardPriceService;
use App\Repository\CardRepository;
use App\Repository\PullRateRepository;
use App\Service\PullRateCalculator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final class CardController
{
    public function __construct(
        private readonly CardRepository $cardRepository,
        private readonly PullRateRepository $pullRateRepository,
        private readonly PullRateCalculator $pullRateCalculator,
        private readonly CardPriceService $cardPriceService,
    ) {
    }

    #[Route('/api/cards', name: 'card_list', methods: ['GET'])]
    public function list(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        CardSearchQuery $query = new CardSearchQuery(),
    ): JsonResponse
    {
        $result = $this->cardRepository->search($query);

        return new JsonResponse([
            'data' => array_map(CardSummaryDto::fromEntity(...), $result['items']),
            'meta' => [
                'total' => $result['total'],
                'page' => $query->page,
                'limit' => $query->limit,
                'totalPages' => (int) ceil($result['total'] / $query->limit),
            ],
        ]);
    }

    #[Route('/api/cards/{id}', name: 'card_show', methods: ['GET'])]
    public function show(Card $card): JsonResponse
    {
        $pullOddsOneIn = null;
        $rarity = $card->getRarity();

        if (null !== $rarity) {
            $pullRate = $this->pullRateRepository->findOneByCardSetAndRarity($card->getCardSet(), $rarity);
            if (null !== $pullRate) {
                $pullOddsOneIn = $this->pullRateCalculator->oddsForSpecificCard($pullRate);
            }
        }

        return new JsonResponse(CardDetailDto::fromEntity($card, $pullOddsOneIn));
    }

    /**
     * The estimated price of the card, or null when there is none. For
     * signed-in users only (see security.yaml): looking at a price may send
     * a request to the service that provides it, and that is not something
     * to hand to every crawler passing by.
     */
    #[Route('/api/cards/{id}/price', name: 'card_price', methods: ['GET'])]
    public function price(Card $card): JsonResponse
    {
        $price = $this->cardPriceService->priceOf($card);

        // The shiny version of a card that has none is not priced (see CardPriceDto).
        $withShinyAmounts = $card->hasSeparateShinyVersion();

        if (null === $price || !$price->hasAmounts($withShinyAmounts)) {
            return new JsonResponse(['price' => null]);
        }

        $sharesNameInSet = $this->cardRepository->count(['cardSet' => $card->getCardSet(), 'name' => $card->getName()]) > 1;

        return new JsonResponse(['price' => CardPriceDto::fromEntity($price, $sharesNameInSet, $withShinyAmounts)]);
    }
}
