<?php

declare(strict_types=1);

namespace App\Tests\Controller;

/**
 * The limits themselves are set in config/packages/rate_limiter.yaml and
 * security.yaml (login_throttling). The counters are reset before each test
 * by AuthWebTestCase.
 */
final class RateLimitTest extends AuthWebTestCase
{
    private const string OTHER_ADDRESS = '203.0.113.7';

    public function testLoginIsBlockedAfterFiveFailedAttemptsEvenWithTheRightPassword(): void
    {
        $user = $this->createUser();

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->login($user->getEmail(), 'wrong guess '.$attempt);
            self::assertResponseStatusCodeSame(401, 'Attempt '.$attempt.' should simply be refused.');
        }

        // Guessing right at this point must not pay off.
        $this->login($user->getEmail());

        self::assertResponseStatusCodeSame(429);
        self::assertSame(['error' => 'Too many login attempts. Try again later.'], $this->responseBody());
        self::assertArrayNotHasKey('token', $this->responseBody());
    }

    public function testFailedLoginsOnOneAccountDoNotLockAnother(): void
    {
        $target = $this->createUser();
        $bystander = $this->createUser();
        for ($attempt = 1; $attempt <= 6; ++$attempt) {
            $this->login($target->getEmail(), 'wrong guess '.$attempt);
        }
        self::assertResponseStatusCodeSame(429);

        $this->login($bystander->getEmail());

        self::assertResponseIsSuccessful();
    }

    public function testAFewMistakesDoNotPreventLoggingIn(): void
    {
        $user = $this->createUser();
        for ($attempt = 1; $attempt <= 4; ++$attempt) {
            $this->login($user->getEmail(), 'wrong guess '.$attempt);
        }

        $this->login($user->getEmail());

        self::assertResponseIsSuccessful();
    }

    public function testRegistrationIsLimitedPerClientAddress(): void
    {
        for ($i = 1; $i <= 10; ++$i) {
            $this->register();
            self::assertResponseStatusCodeSame(201, 'Registration '.$i.' should be accepted.');
        }

        $this->register();

        self::assertResponseStatusCodeSame(429);
        self::assertArrayHasKey('error', $this->responseBody());
        // Tells a well-behaved client how many seconds to wait.
        self::assertGreaterThan(0, (int) $this->client->getResponse()->headers->get('Retry-After'));

        // Someone else is not affected.
        $this->register(self::OTHER_ADDRESS);
        self::assertResponseStatusCodeSame(201);
    }

    public function testRefreshIsLimitedPerClientAddress(): void
    {
        for ($i = 1; $i <= 30; ++$i) {
            $this->client->request('POST', '/api/auth/refresh');
            self::assertResponseStatusCodeSame(401, 'Refresh '.$i.' should reach the token check.');
        }

        $this->client->request('POST', '/api/auth/refresh');

        self::assertResponseStatusCodeSame(429);
        self::assertSame(['error' => 'Too many requests.'], $this->responseBody());
        self::assertGreaterThan(0, (int) $this->client->getResponse()->headers->get('Retry-After'));

        $this->client->request('POST', '/api/auth/refresh', server: ['REMOTE_ADDR' => self::OTHER_ADDRESS]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testTheCatalogueIsNotRateLimited(): void
    {
        for ($i = 1; $i <= 40; ++$i) {
            $this->client->request('GET', '/api/games');
        }

        self::assertResponseIsSuccessful();
    }

    private function register(?string $fromAddress = null): void
    {
        $this->client->jsonRequest(
            'POST',
            '/api/auth/register',
            ['email' => 'user-'.uniqid().'@example.com', 'password' => self::TEST_PASSWORD],
            null === $fromAddress ? [] : ['REMOTE_ADDR' => $fromAddress],
        );
    }
}
