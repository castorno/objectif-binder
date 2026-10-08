<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\CardIdentityDto;
use App\Dto\CardIdentitySummaryDto;
use App\Dto\IdentitySearchQuery;
use App\Entity\CardIdentity;
use App\Repository\CardIdentityRepository;
use App\Repository\CardRepository;
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
    ) {
    }

    #[Route('/api/identities', name: 'identity_list', methods: ['GET'])]
    public function list(
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        IdentitySearchQuery $query = new IdentitySearchQuery(),
    ): JsonResponse {
        $result = $this->cardIdentityRepository->search($query);
        $cardCounts = $this->cardIdentityRepository->countCardsByIdentity($result['items']);
        $imageUrls = $this->cardIdentityRepository->findImageUrlsByIdentity($result['items']);

        return new JsonResponse([
            'data' => array_map(
                static fn (CardIdentity $identity): CardIdentitySummaryDto => CardIdentitySummaryDto::fromEntity(
                    $identity,
                    $cardCounts[(string) $identity->getId()] ?? 0,
                    $imageUrls[(string) $identity->getId()] ?? null,
                ),
                $result['items'],
            ),
            'meta' => [
                'total' => $result['total'],
                'page' => $query->page,
                'limit' => $query->limit,
                'totalPages' => (int) ceil($result['total'] / $query->limit),
                // The cards no entry leads to: the grouped view offers them
                // as one more entry, so that nothing is out of reach.
                'cardsWithoutIdentity' => $this->cardRepository->countSearch($query->cardsWithoutIdentity()),
            ],
        ]);
    }

    #[Route('/api/identities/{id}', name: 'identity_show', methods: ['GET'])]
    public function show(CardIdentity $identity): JsonResponse
    {
        return new JsonResponse(CardIdentityDto::fromEntity($identity));
    }
}
