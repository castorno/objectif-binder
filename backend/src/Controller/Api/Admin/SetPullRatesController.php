<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Dto\SetPullRatesRequest;
use App\Entity\CardSet;
use App\Exception\UnknownRarityException;
use App\Service\SetPullRatesService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pull rates of a set, entered by hand: publishers rarely give them, and no
 * source serves them. For administrators only: every route under /api/admin
 * requires the role (see security.yaml).
 */
final class SetPullRatesController
{
    public function __construct(
        private readonly SetPullRatesService $setPullRatesService,
    ) {
    }

    #[Route('/api/admin/sets/{id}/pull-rates', name: 'admin_set_pull_rates_show', methods: ['GET'])]
    public function show(CardSet $cardSet): JsonResponse
    {
        return $this->answer($cardSet);
    }

    /**
     * PUT: the body is the whole list of rates of the set, so sending it
     * twice changes nothing more, and a rarity left out loses its rate.
     */
    #[Route('/api/admin/sets/{id}/pull-rates', name: 'admin_set_pull_rates_put', methods: ['PUT'], format: 'json')]
    public function put(CardSet $cardSet, #[MapRequestPayload(acceptFormat: 'json')] SetPullRatesRequest $request): JsonResponse
    {
        try {
            $this->setPullRatesService->replace($cardSet, $request->rates, $request->source);
        } catch (UnknownRarityException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return $this->answer($cardSet);
    }

    private function answer(CardSet $cardSet): JsonResponse
    {
        $rows = $this->setPullRatesService->ratesOf($cardSet);

        $source = null;
        $updatedAt = null;
        foreach ($rows as $row) {
            $pullRate = $row['pullRate'];
            if (null === $pullRate) {
                continue;
            }
            // One source is entered for the whole set; rates seeded another
            // way may each have theirs, and the first one found is shown.
            $source ??= $pullRate->getSource();
            $updatedAt = max($updatedAt, $pullRate->getUpdatedAt());
        }

        return new JsonResponse([
            'set' => ['id' => (string) $cardSet->getId(), 'name' => $cardSet->getName(), 'code' => $cardSet->getCode()],
            'source' => $source,
            'updatedAt' => $updatedAt?->format(\DATE_ATOM),
            'rarities' => array_map(
                static fn (array $row): array => [
                    'id' => (string) $row['rarity']->getId(),
                    'name' => $row['rarity']->getName(),
                    // How many cards of the set have this rarity.
                    'cardsInSet' => $row['cardsInSet'],
                    'rate' => null === $row['pullRate'] ? null : [
                        'cards' => $row['pullRate']->getCardCount(),
                        'boosters' => $row['pullRate']->getBoosterCount(),
                    ],
                ],
                $rows,
            ),
        ]);
    }
}
