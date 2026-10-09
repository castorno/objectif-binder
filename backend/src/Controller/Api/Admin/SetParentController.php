<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Dto\SetParentRequest;
use App\Entity\CardSet;
use App\Exception\InvalidParentSetException;
use App\Repository\CardSetRepository;
use App\Service\CardSetParentService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Links a set to the one its cards come in the boosters of. No source says
 * which sets belong together, so an administrator does.
 */
final class SetParentController
{
    public function __construct(
        private readonly CardSetParentService $cardSetParentService,
        private readonly CardSetRepository $cardSetRepository,
    ) {
    }

    #[Route('/api/admin/sets/{id}/parent', name: 'admin_set_parent_put', methods: ['PUT'], format: 'json')]
    public function put(CardSet $cardSet, #[MapRequestPayload(acceptFormat: 'json')] SetParentRequest $request): JsonResponse
    {
        $parent = null;
        if (null !== $request->parentId) {
            $parent = $this->cardSetRepository->find($request->parentId);
            if (null === $parent) {
                throw new UnprocessableEntityHttpException('The parent set does not exist.');
            }
        }

        try {
            $this->cardSetParentService->setParent($cardSet, $parent);
        } catch (InvalidParentSetException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        return new JsonResponse(['parentCode' => $parent?->getCode()]);
    }
}
