<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\RefreshToken;
use App\Entity\User;
use Symfony\Component\BrowserKit\Cookie as BrowserCookie;
use Symfony\Component\HttpFoundation\Cookie;

final class RefreshTokenTest extends AuthWebTestCase
{
    private const string COOKIE = 'refresh_token';

    public function testLoginSetsTheRefreshTokenInALockedDownCookieOnly(): void
    {
        $user = $this->createUser();

        $this->login($user->getEmail());

        self::assertResponseIsSuccessful();
        // The JWT goes in the body; the refresh token must not.
        self::assertSame(['token'], array_keys($this->responseBody()));

        $cookie = $this->responseCookie();
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly(), 'JavaScript must not be able to read the refresh token.');
        self::assertTrue($cookie->isSecure());
        self::assertSame('strict', $cookie->getSameSite());
        self::assertSame('/api/auth', $cookie->getPath());
        self::assertEqualsWithDelta(time() + 14 * 86400, $cookie->getExpiresTime(), 5);
    }

    public function testOnlyAHashOfTheRefreshTokenIsStored(): void
    {
        $user = $this->createUser();

        $this->login($user->getEmail());

        $stored = $this->storedTokens($user);
        self::assertCount(1, $stored);
        self::assertStringStartsWith('sha256$', $stored[0]);
        self::assertStringNotContainsString($this->currentRefreshToken(), $stored[0]);
    }

    public function testRefreshIssuesANewJwtAndReplacesTheRefreshToken(): void
    {
        $user = $this->createUser();
        $this->login($user->getEmail());
        $firstRefreshToken = $this->currentRefreshToken();

        $this->refresh();

        self::assertResponseIsSuccessful();
        self::assertSame(['token'], array_keys($this->responseBody()));
        self::assertNotSame($firstRefreshToken, $this->currentRefreshToken());
        self::assertCount(1, $this->storedTokens($user), 'The replaced token must be gone, not kept alongside.');

        $this->client->request('GET', '/api/me', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->responseBody()['token']]);
        self::assertResponseIsSuccessful();
    }

    public function testARefreshTokenCannotBeUsedTwice(): void
    {
        $user = $this->createUser();
        $this->login($user->getEmail());
        $spentRefreshToken = $this->currentRefreshToken();
        $this->refresh();

        // What a thief replaying a captured token would send.
        $this->sendRefreshToken($spentRefreshToken);
        $this->refresh();

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'Invalid or expired refresh token.'], $this->responseBody());
    }

    public function testRefreshRequiresARefreshToken(): void
    {
        $this->refresh();

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'Invalid or expired refresh token.'], $this->responseBody());
    }

    public function testRefreshRejectsAnUnknownToken(): void
    {
        $this->sendRefreshToken(bin2hex(random_bytes(64)));

        $this->refresh();

        // Same answer as for a missing token: nothing tells them apart.
        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'Invalid or expired refresh token.'], $this->responseBody());
    }

    public function testRefreshRejectsAnExpiredToken(): void
    {
        $user = $this->createUser();
        $this->login($user->getEmail());
        $this->em->createQuery('UPDATE '.RefreshToken::class.' t SET t.valid = :past WHERE t.username = :email')
            ->execute(['past' => new \DateTime('-1 minute'), 'email' => $user->getEmail()]);
        $this->em->clear();

        $this->refresh();

        self::assertResponseStatusCodeSame(401);
    }

    public function testRefreshRejectsTheTokenOfADeletedUser(): void
    {
        $user = $this->createUser();
        $this->login($user->getEmail());
        $this->em->remove($this->em->find(User::class, $user->getId()));
        $this->em->flush();

        $this->refresh();

        self::assertResponseStatusCodeSame(401);
    }

    public function testLogoutDeletesTheRefreshTokenAndClearsTheCookie(): void
    {
        $user = $this->createUser();
        $this->login($user->getEmail());
        $refreshToken = $this->currentRefreshToken();

        $this->logout();

        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $this->storedTokens($user));
        $cleared = $this->responseCookie();
        self::assertNotNull($cleared);
        self::assertLessThan(time(), $cleared->getExpiresTime());

        // Even a copy of the token kept aside is now useless.
        $this->sendRefreshToken($refreshToken);
        $this->refresh();
        self::assertResponseStatusCodeSame(401);
    }

    public function testLogoutOnlyEndsTheCurrentSession(): void
    {
        $user = $this->createUser();
        $this->login($user->getEmail());
        $otherDevice = $this->currentRefreshToken();
        $this->forgetCookies();
        $this->login($user->getEmail());

        $this->logout();

        self::assertCount(1, $this->storedTokens($user));
        $this->sendRefreshToken($otherDevice);
        $this->refresh();
        self::assertResponseIsSuccessful();
    }

    public function testLogoutWithoutASessionAnswersTheSameAndRevealsNothing(): void
    {
        $this->logout();
        self::assertResponseStatusCodeSame(204);

        $this->sendRefreshToken(bin2hex(random_bytes(64)));
        $this->logout();
        self::assertResponseStatusCodeSame(204);
    }

    public function testLogoutWorksOnceTheAccessTokenHasExpired(): void
    {
        $user = $this->createUser();
        $this->login($user->getEmail());

        // No Authorization header: the usual state of a tab left open.
        $this->logout();

        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $this->storedTokens($user));
    }

    public function testSigningInAgainFromTheSameBrowserReplacesItsSession(): void
    {
        $user = $this->createUser();
        $this->login($user->getEmail());

        $this->login($user->getEmail());

        self::assertCount(1, $this->storedTokens($user));
    }

    public function testAUserKeepsAtMostFiveSessions(): void
    {
        $user = $this->createUser();

        // Six different devices: each signs in without a cookie of its own.
        for ($i = 0; $i < 6; ++$i) {
            $this->forgetCookies();
            $this->login($user->getEmail());
        }

        self::assertCount(5, $this->storedTokens($user));
    }

    public function testRefreshAndLogoutOnlyAnswerToPost(): void
    {
        // A GET can be triggered by a link or an image tag on any page.
        $this->client->request('GET', '/api/auth/refresh');
        self::assertResponseStatusCodeSame(405);

        $this->client->request('GET', '/api/auth/logout');
        self::assertResponseStatusCodeSame(405);
    }

    private function refresh(): void
    {
        $this->client->request('POST', '/api/auth/refresh');
    }

    private function logout(): void
    {
        $this->client->request('POST', '/api/auth/logout');
    }

    /** Makes the client behave like a browser that has never signed in. */
    private function forgetCookies(): void
    {
        $this->client->getCookieJar()->clear();
    }

    /** The refresh token the client currently holds, as a browser would. */
    private function currentRefreshToken(): string
    {
        $cookie = $this->client->getCookieJar()->get(self::COOKIE, '/api/auth');
        self::assertNotNull($cookie, 'The client holds no refresh token cookie.');

        return $cookie->getValue();
    }

    /** Replaces the refresh token the client will send next. */
    private function sendRefreshToken(string $value): void
    {
        $this->forgetCookies();
        $this->client->getCookieJar()->set(new BrowserCookie(self::COOKIE, $value, path: '/api/auth', domain: 'localhost', secure: true));
    }

    /** The refresh token cookie set or cleared by the last response, if any. */
    private function responseCookie(): ?Cookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if (self::COOKIE === $cookie->getName()) {
                return $cookie;
            }
        }

        return null;
    }

    /**
     * @return list<string> the refresh tokens of the user as stored in the database
     */
    private function storedTokens(User $user): array
    {
        return $this->em->getConnection()->fetchFirstColumn(
            'SELECT refresh_token FROM refresh_token WHERE username = :email',
            ['email' => $user->getEmail()],
        );
    }
}
