<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Dto\SetPullRatesRequest;
use App\Entity\CardSet;
use App\Exception\PullRatesOfSubSetException;
use App\Exception\UnknownRarityException;
use App\Repository\CardSetRepository;
use App\Service\SetPullRatesService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
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
        private readonly CardSetRepository $cardSetRepository,
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
        } catch (PullRatesOfSubSetException $exception) {
            throw new ConflictHttpException($exception->getMessage(), $exception);
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
            'set' => $this->summary($cardSet),
            // The rarity all the cards of the set are given, when an administrator named one.
            'forcedRarity' => $cardSet->getForcedRarity()?->getName(),
            // The set whose boosters hold these cards: the rates are entered there.
            'parent' => null === $cardSet->getParent() ? null : $this->summary($cardSet->getParent()),
            // The sets released in the boosters of this one: their cards count here.
            'subSets' => array_map($this->summary(...), $this->cardSetRepository->findSubSets($cardSet)),
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

    /**
     * @return array{id: string, name: string, code: string}
     */
    private function summary(CardSet $cardSet): array
    {
        return ['id' => (string) $cardSet->getId(), 'name' => $cardSet->getName(), 'code' => $cardSet->getCode()];
    }
}
