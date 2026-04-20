<?php

namespace App\Tests\Functional;

final class AuthControllerTest extends ApiTestCase
{
    public function testAuthSuccess(): void
    {
        $this->postJson('/api/v1/auth', [
            'username' => 'user@example.com',
            'password' => 'user123',
        ]);

        $response = $this->client->getResponse();

        self::assertResponseIsSuccessful();
        self::assertJson($response->getContent());

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('token', $payload);
        self::assertIsString($payload['token']);
        self::assertNotSame('', $payload['token']);
        self::assertSame(['ROLE_USER'], $payload['roles']);
    }

    public function testAuthFailsWithInvalidPassword(): void
    {
        $this->postJson('/api/v1/auth', [
            'username' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response = $this->client->getResponse();

        self::assertResponseStatusCodeSame(401);
        self::assertJson($response->getContent());

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('message', $payload);
        self::assertArrayNotHasKey('token', $payload);
    }
}
