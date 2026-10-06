<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegistrationTest extends AuthWebTestCase
{
    public function testRegisterCreatesTheAccountWithoutSigningIn(): void
    {
        $email = $this->uniqueEmail();

        $this->register(['email' => $email, 'password' => self::TEST_PASSWORD]);

        self::assertResponseStatusCodeSame(201);
        $user = $this->findUser($email);
        self::assertNotNull($user);
        // No token and nothing about the password in the answer.
        self::assertSame(['id' => (string) $user->getId(), 'email' => $email], $this->responseBody());
    }

    public function testRegisterStoresAHashAndNeverThePassword(): void
    {
        $email = $this->uniqueEmail();

        $this->register(['email' => $email, 'password' => self::TEST_PASSWORD]);

        $user = $this->findUser($email);
        self::assertNotSame(self::TEST_PASSWORD, $user->getPassword());
        self::assertStringNotContainsString(self::TEST_PASSWORD, $user->getPassword());
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, self::TEST_PASSWORD));
    }

    public function testARegisteredUserCanLogIn(): void
    {
        $email = $this->uniqueEmail();
        $this->register(['email' => $email, 'password' => self::TEST_PASSWORD]);

        $this->login($email);

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('token', $this->responseBody());
    }

    public function testEmailIsStoredInLowerCaseAndLoginIgnoresItsCase(): void
    {
        $email = $this->uniqueEmail();

        $this->register(['email' => '  '.strtoupper($email).' ', 'password' => self::TEST_PASSWORD]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame($email, $this->responseBody()['email']);

        $this->login(ucfirst($email));

        self::assertResponseIsSuccessful();
    }

    public function testRegisterRefusesAnEmailAlreadyInUseWhateverItsCase(): void
    {
        $email = $this->uniqueEmail();
        $this->register(['email' => $email, 'password' => self::TEST_PASSWORD]);

        $this->register(['email' => strtoupper($email), 'password' => 'another long passphrase 42']);

        self::assertResponseStatusCodeSame(409);
        self::assertSame(['error' => 'This e-mail address is already registered.'], $this->responseBody());
        // The first account is untouched.
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($this->findUser($email), self::TEST_PASSWORD));
    }

    public function testRegisterIgnoresFieldsItDoesNotExpect(): void
    {
        $email = $this->uniqueEmail();

        // A client must not be able to grant itself a role or pick its id.
        $this->register([
            'email' => $email,
            'password' => self::TEST_PASSWORD,
            'roles' => ['ROLE_ADMIN'],
            'id' => '00000000-0000-0000-0000-000000000000',
        ]);

        self::assertResponseStatusCodeSame(201);
        $user = $this->findUser($email);
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertNotSame('00000000-0000-0000-0000-000000000000', (string) $user->getId());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPayloads')]
    public function testRegisterRejectsInvalidInput(array $payload, string $faultyField): void
    {
        $this->register($payload);

        self::assertResponseStatusCodeSame(422);
        $body = $this->responseBody();
        self::assertSame('Validation failed.', $body['error']);
        self::assertSame([$faultyField], array_keys($body['violations']));
        self::assertNotEmpty($body['violations'][$faultyField]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'malformed e-mail' => [['email' => 'not-an-email', 'password' => self::TEST_PASSWORD], 'email'];
        yield 'blank e-mail' => [['email' => '   ', 'password' => self::TEST_PASSWORD], 'email'];
        yield 'e-mail too long' => [['email' => str_repeat('a', 180).'@example.com', 'password' => self::TEST_PASSWORD], 'email'];
        yield 'missing e-mail' => [['password' => self::TEST_PASSWORD], 'email'];
        yield 'password too short' => [['email' => 'someone@example.com', 'password' => 'Xk9#mQ2!'], 'password'];
        yield 'password long but trivial' => [['email' => 'someone@example.com', 'password' => 'aaaaaaaaaaaa'], 'password'];
        yield 'password too long' => [['email' => 'someone@example.com', 'password' => str_repeat('correct horse ', 10)], 'password'];
        yield 'missing password' => [['email' => 'someone@example.com'], 'password'];
        yield 'password that is not text' => [['email' => 'someone@example.com', 'password' => 123456789012], 'password'];
    }

    public function testRegisterCreatesNothingWhenInputIsInvalid(): void
    {
        $email = $this->uniqueEmail();

        $this->register(['email' => $email, 'password' => 'short']);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->findUser($email));
    }

    public function testRegisterRejectsMalformedJson(): void
    {
        $this->client->request('POST', '/api/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: '{"email":');

        self::assertResponseStatusCodeSame(400);
        self::assertArrayHasKey('error', $this->responseBody());
    }

    public function testRegisterOnlyAcceptsJson(): void
    {
        $email = $this->uniqueEmail();

        // What a plain HTML form on another site would be able to send.
        $this->client->request('POST', '/api/auth/register', ['email' => $email, 'password' => self::TEST_PASSWORD]);

        self::assertResponseStatusCodeSame(415);
        self::assertNull($this->findUser($email));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function register(array $payload): void
    {
        $this->client->jsonRequest('POST', '/api/auth/register', $payload);
    }

    private function uniqueEmail(): string
    {
        return 'user-'.uniqid().'@example.com';
    }

    private function findUser(string $email): ?User
    {
        $this->em->clear();

        return static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
    }
}
