<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\RegisterRequest;
use App\Dto\UserDto;
use App\Entity\User;
use App\Exception\EmailAlreadyRegisteredException;
use App\Service\UserRegistrationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AuthController
{
    public function __construct(
        private readonly UserRegistrationService $userRegistrationService,
    ) {
    }

    /**
     * Creates the account without signing in: tokens are only ever issued by
     * the login endpoint. JSON only: a form on another site cannot post JSON
     * without a CORS preflight, which this API refuses to unknown origins.
     */
    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'], format: 'json')]
    public function register(#[MapRequestPayload(acceptFormat: 'json')] RegisterRequest $request): JsonResponse
    {
        try {
            $user = $this->userRegistrationService->register($request->email, $request->password);
        } catch (EmailAlreadyRegisteredException $exception) {
            throw new ConflictHttpException($exception->getMessage(), $exception);
        }

        return new JsonResponse(UserDto::fromEntity($user), JsonResponse::HTTP_CREATED);
    }

    /**
     * Credentials are checked by the firewall's json_login authenticator,
     * which answers before this method is reached. It only takes over JSON
     * requests, so getting here means the body was sent as something else.
     */
    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(): never
    {
        throw new BadRequestHttpException('Expected a JSON body with "email" and "password".');
    }

    /**
     * Both routes below are answered by the firewall (refresh_jwt and logout)
     * before any controller runs. They are declared here so that the paths
     * exist for the router, and only for POST: a GET, which a mere link or
     * image tag can trigger, never reaches the firewall.
     */
    #[Route('/api/auth/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    #[Route('/api/auth/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function handledByFirewall(): never
    {
        throw new \LogicException('This route should have been intercepted by the security firewall.');
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse(UserDto::fromEntity($user));
    }
}
