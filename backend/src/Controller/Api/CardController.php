<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\CardDetailDto;
use App\Dto\CardSearchQuery;
use App\Dto\CardSummaryDto;
use App\Entity\Card;
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
}
