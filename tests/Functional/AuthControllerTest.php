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
        self::assertArrayHasKey('refresh_token', $payload);
        self::assertIsString($payload['token']);
        self::assertIsString($payload['refresh_token']);
        self::assertNotSame('', $payload['token']);
        self::assertNotSame('', $payload['refresh_token']);
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

    public function testRefreshTokenSuccess(): void
    {
        $this->postJson('/api/v1/auth', [
            'username' => 'user@example.com',
            'password' => 'user123',
        ]);

        $authPayload = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/v1/token/refresh', [
            'refresh_token' => $authPayload['refresh_token'],
        ]);

        $response = $this->client->getResponse();

        self::assertResponseIsSuccessful();
        self::assertJson($response->getContent());

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('token', $payload);
        self::assertArrayHasKey('refresh_token', $payload);
        self::assertIsString($payload['token']);
        self::assertIsString($payload['refresh_token']);
        self::assertNotSame('', $payload['token']);
        self::assertNotSame('', $payload['refresh_token']);
    }

    public function testRememberMeAuthIssuesTokenForTrustedCaller(): void
    {
        $this->postJson('/api/v1/auth/remember', [
            'username' => 'user@example.com',
        ], [
            'HTTP_X_INTERNAL_AUTH_SECRET' => 'study-on-internal-auth-secret',
        ]);

        $response = $this->client->getResponse();

        self::assertResponseIsSuccessful();
        self::assertJson($response->getContent());

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('user@example.com', $payload['username']);
        self::assertIsString($payload['token']);
        self::assertIsString($payload['refresh_token']);
        self::assertNotSame('', $payload['token']);
        self::assertNotSame('', $payload['refresh_token']);
        self::assertSame(['ROLE_USER'], $payload['roles']);
        self::assertSame(5000, $payload['balance']);
    }

    public function testRememberMeAuthRejectsInvalidSecret(): void
    {
        $this->postJson('/api/v1/auth/remember', [
            'username' => 'user@example.com',
        ], [
            'HTTP_X_INTERNAL_AUTH_SECRET' => 'wrong-secret',
        ]);

        $response = $this->client->getResponse();

        self::assertResponseStatusCodeSame(403);
        self::assertJson($response->getContent());

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('Invalid internal auth secret.', $payload['message']);
    }
}
