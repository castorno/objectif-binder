<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\CardIdentityDto;
use App\Dto\IdentitySearchQuery;
use App\Dto\PaginatedDto;
use App\Entity\CardIdentity;
use App\Repository\CardIdentityRepository;
use App\Repository\CardRepository;
use App\Service\CardIdentitySummaryService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The grouped view of the catalog: one entry per identity instead of one per
 * card. The cards of an entry are a search like any other, on
 * GET /api/cards?identity=<id>.
 */
final class CardIdentityController
{
    public function __construct(
        private readonly CardIdentityRepository $cardIdentityRepository,
        private readonly CardRepository $cardRepository,
        private readonly CardIdentitySummaryService $cardIdentitySummaryService,
    ) {
    }

    #[Route('/api/identities', name: 'identity_list', methods: ['GET'])]
    public function list(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        IdentitySearchQuery $query = new IdentitySearchQuery(),
    ): JsonResponse {
        $result = $this->cardIdentityRepository->search($query);

        return new JsonResponse(new PaginatedDto(
            $this->cardIdentitySummaryService->summarize($result['items']),
            $result['total'],
            $query->page,
            $query->limit,
            [
                // The cards no entry leads to: the grouped view offers them
                // as one more entry, so that nothing is out of reach.
                'cardsWithoutIdentity' => null === ($others = $query->cardsWithoutIdentity()) ? 0 : $this->cardRepository->countSearch($others),
            ],
        ));
    }

    #[Route('/api/identities/{id}', name: 'identity_show', methods: ['GET'])]
    public function show(CardIdentity $identity): JsonResponse
    {
        return new JsonResponse(CardIdentityDto::fromEntity($identity));
    }
}
