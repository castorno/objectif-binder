<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\UserDto;
use App\Dto\UserPreferencesRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * What the signed-in user chose about how the application behaves for them.
 * Like the collection, whose account it is comes from the access token.
 */
final class UserPreferencesController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/me/preferences', name: 'api_me_preferences_put', methods: ['PUT'], format: 'json')]
    public function put(#[CurrentUser] User $user, #[MapRequestPayload(acceptFormat: 'json')] UserPreferencesRequest $request): JsonResponse
    {
        $user->setDefaultCondition($request->defaultCondition);
        $this->entityManager->flush();

        return new JsonResponse(UserDto::fromEntity($user));
    }
}
