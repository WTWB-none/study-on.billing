<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;

final class RegisterControllerTest extends ApiTestCase
{
    public function testRegisterSuccess(): void
    {
        $this->postJson('/api/v1/register', [
            'email' => 'new-user@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->client->getResponse();

        self::assertResponseIsSuccessful();
        self::assertJson($response->getContent());

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('token', $payload);
        self::assertSame(['ROLE_USER'], $payload['roles']);

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['email' => 'new-user@example.com']);

        self::assertInstanceOf(User::class, $user);
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertSame(0.0, $user->getBalance());
    }

    public function testRegisterFailsWithInvalidPayload(): void
    {
        $this->postJson('/api/v1/register', [
            'email' => 'invalid-email',
            'password' => '123',
        ]);

        $response = $this->client->getResponse();

        self::assertResponseStatusCodeSame(400);
        self::assertJson($response->getContent());

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('Validation failed.', $payload['message']);
        self::assertCount(2, $payload['errors']);
    }

    public function testRegisterFailsForDuplicateEmail(): void
    {
        $this->postJson('/api/v1/register', [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->client->getResponse();

        self::assertResponseStatusCodeSame(409);
        self::assertJson($response->getContent());

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('User with this email already exists.', $payload['message']);
        self::assertSame('email', $payload['errors'][0]['field']);
    }
}
