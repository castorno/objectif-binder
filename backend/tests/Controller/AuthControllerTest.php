<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

final class AuthControllerTest extends AuthWebTestCase
{
    public function testLoginReturnsAShortLivedTokenForValidCredentials(): void
    {
        $user = $this->createUser();

        $this->login($user->getEmail());

        self::assertResponseIsSuccessful();
        $body = $this->responseBody();
        self::assertSame(['token'], array_keys($body));

        $payload = $this->decodePayload($body['token']);
        self::assertSame($user->getEmail(), $payload['username']);
        self::assertEqualsWithDelta(time() + 900, $payload['exp'], 5);
        self::assertStringNotContainsString($user->getPassword(), json_encode($payload));
    }

    public function testLoginAnswersTheSameForAWrongPasswordAndAnUnknownEmail(): void
    {
        $user = $this->createUser();

        $this->login($user->getEmail(), 'not the password');
        self::assertResponseStatusCodeSame(401);
        $wrongPassword = $this->client->getResponse()->getContent();

        $this->login('nobody-'.uniqid().'@example.com', self::TEST_PASSWORD);
        self::assertResponseStatusCodeSame(401);
        $unknownEmail = $this->client->getResponse()->getContent();

        // Otherwise the endpoint could be used to find out who has an account.
        self::assertSame($wrongPassword, $unknownEmail);
        self::assertSame(['error' => 'Invalid credentials.'], json_decode($wrongPassword, true));
    }

    public function testLoginRejectsABodyThatIsNotJson(): void
    {
        $user = $this->createUser();

        $this->client->request('POST', '/api/auth/login', ['email' => $user->getEmail(), 'password' => self::TEST_PASSWORD]);

        self::assertResponseStatusCodeSame(400);
        self::assertArrayHasKey('error', $this->responseBody());
    }

    public function testLoginRejectsIncompleteCredentials(): void
    {
        $this->client->jsonRequest('POST', '/api/auth/login', ['email' => 'someone@example.com']);

        self::assertResponseStatusCodeSame(400);
        self::assertArrayHasKey('error', $this->responseBody());
    }

    public function testMeReturnsTheAuthenticatedUserAndNothingSensitive(): void
    {
        $user = $this->createUser();
        $this->login($user->getEmail());
        $token = $this->responseBody()['token'];

        $this->requestMe($token);

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['id' => (string) $user->getId(), 'email' => $user->getEmail()],
            $this->responseBody(),
        );
    }

    public function testMeRequiresAToken(): void
    {
        $this->client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('WWW-Authenticate', 'Bearer');
        self::assertSame(['error' => 'Authentication required.'], $this->responseBody());
    }

    public function testMeRejectsATokenWhoseSignatureDoesNotMatch(): void
    {
        $user = $this->createUser();
        [$header, , $signature] = explode('.', $this->tokenFor($user));
        // Same header and signature, but a payload claiming another identity.
        $forgedPayload = $this->base64UrlEncode(json_encode([
            'username' => 'admin@example.com',
            'roles' => ['ROLE_ADMIN'],
            'iat' => time(),
            'exp' => time() + 900,
        ]));

        $this->requestMe($header.'.'.$forgedPayload.'.'.$signature);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'Invalid token.'], $this->responseBody());
    }

    public function testMeRejectsAnUnsignedToken(): void
    {
        $user = $this->createUser();
        $header = $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'none']));
        $payload = $this->base64UrlEncode(json_encode([
            'username' => $user->getEmail(),
            'roles' => ['ROLE_USER'],
            'iat' => time(),
            'exp' => time() + 900,
        ]));

        $this->requestMe($header.'.'.$payload.'.');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'Invalid token.'], $this->responseBody());
    }

    public function testMeRejectsAnExpiredToken(): void
    {
        $user = $this->createUser();
        $expired = static::getContainer()->get(JWTTokenManagerInterface::class)
            ->createFromPayload($user, ['exp' => time() - 60]);

        $this->requestMe($expired);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'Expired token.'], $this->responseBody());
    }

    public function testMeRejectsTheTokenOfADeletedUser(): void
    {
        $user = $this->createUser();
        $token = $this->tokenFor($user);
        $this->em->remove($user);
        $this->em->flush();

        // The token itself is still valid; the account behind it is gone.
        $this->requestMe($token);

        self::assertResponseStatusCodeSame(401);
    }

    public function testATokenInTheQueryStringIsIgnored(): void
    {
        $user = $this->createUser();

        // URLs end up in logs and browser history: only the header is accepted.
        $this->client->request('GET', '/api/me?bearer='.$this->tokenFor($user));

        self::assertResponseStatusCodeSame(401);
    }

    private function tokenFor(User $user): string
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
    }

    private function requestMe(string $token): void
    {
        $this->client->request('GET', '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $token): array
    {
        $payload = explode('.', $token)[1];

        return json_decode(base64_decode(strtr($payload, '-_', '+/')), true, flags: \JSON_THROW_ON_ERROR);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
