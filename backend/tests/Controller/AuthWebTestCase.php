<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Shared ground for the authentication tests: an HTTP client, throwaway users
 * and a database transaction rolled back after each test.
 */
abstract class AuthWebTestCase extends WebTestCase
{
    /**
     * Not a secret: a made-up password given to the throwaway users these
     * tests create, which are rolled back with the test. It only needs to
     * satisfy the registration rules (length and strength).
     */
    protected const string TEST_PASSWORD = 'correct horse battery staple';

    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        // HTTPS: the refresh cookie is flagged Secure, so like a browser the
        // test client only sends it back over a secure connection.
        $this->client = static::createClient(server: ['HTTPS' => 'on']);
        // Several requests per test share one kernel, hence one database
        // connection, so the transaction below covers all of them.
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
        // Rate limit counters live in a cache that outlasts a test run: start
        // each test from zero, or earlier attempts would count against it.
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        $this->em->close();

        parent::tearDown();
    }

    /** A new user with a unique e-mail, whose password is TEST_PASSWORD. */
    protected function createUser(): User
    {
        $user = new User('user-'.uniqid().'@example.com');
        $hasher = static::getContainer()->get(PasswordHasherFactoryInterface::class)->getPasswordHasher($user);
        $user->setPassword($hasher->hash(self::TEST_PASSWORD));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    protected function login(string $email, string $password = self::TEST_PASSWORD): void
    {
        $this->client->jsonRequest('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseBody(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
