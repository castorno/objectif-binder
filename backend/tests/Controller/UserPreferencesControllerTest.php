<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;

final class UserPreferencesControllerTest extends AuthWebTestCase
{
    public function testPutRequiresAuthentication(): void
    {
        $this->put(null, ['defaultCondition' => 'near_mint']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testPutSetsThenClearsTheDefaultConditionOfTheUserOnly(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();
        $token = $this->tokenFor($user);

        $this->put($token, ['defaultCondition' => 'near_mint']);

        self::assertResponseIsSuccessful();
        self::assertSame('near_mint', $this->responseBody()['defaultCondition']);
        $this->client->request('GET', '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
        self::assertSame('near_mint', $this->responseBody()['defaultCondition']);
        $this->client->request('GET', '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->tokenFor($other)]);
        self::assertNull($this->responseBody()['defaultCondition']);

        // The body is the whole state: nothing in it, nothing kept.
        $this->put($token, []);

        self::assertResponseIsSuccessful();
        self::assertNull($this->responseBody()['defaultCondition']);
    }

    public function testPutRejectsAConditionThatDoesNotExist(): void
    {
        $user = $this->createUser();

        $this->put($this->tokenFor($user), ['defaultCondition' => 'brand_new']);

        self::assertResponseStatusCodeSame(422);
    }

    private function tokenFor(User $user): string
    {
        $this->login($user->getEmail());

        return $this->responseBody()['token'];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function put(?string $token, array $payload): void
    {
        $this->client->request(
            'PUT',
            '/api/me/preferences',
            server: ['CONTENT_TYPE' => 'application/json'] + (null === $token ? [] : ['HTTP_AUTHORIZATION' => 'Bearer '.$token]),
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }
}
