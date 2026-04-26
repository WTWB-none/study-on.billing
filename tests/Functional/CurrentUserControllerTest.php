<?php

namespace App\Tests\Functional;

final class CurrentUserControllerTest extends ApiTestCase
{
    public function testGetCurrentUserReturnsAuthenticatedUser(): void
    {
        $this->postJson('/api/v1/auth', [
            'username' => 'user@example.com',
            'password' => 'user123',
        ]);

        $authPayload = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('token', $authPayload);

        $this->client->request(
            'GET',
            '/api/v1/users/current',
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $authPayload['token']),
            ]
        );

        $response = $this->client->getResponse();

        self::assertResponseIsSuccessful();
        self::assertJson($response->getContent());

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('user@example.com', $payload['username']);
        self::assertSame(['ROLE_USER'], $payload['roles']);
        self::assertSame(0, $payload['balance']);
    }
}
