<?php

namespace App\Controller;

use LogicException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController
{
    #[OA\Post(
        path: '/api/v1/auth',
        tags: ['Auth'],
        summary: 'Authenticate user',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'developer@intaro.ru'),
                    new OA\Property(property: 'password', type: 'string', example: 'user123'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'JWT token issued',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_USER']),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Invalid credentials'),
        ]
    )]
    #[Route('/api/v1/auth', name: 'api_v1_auth', methods: ['POST'])]
    public function __invoke(): Response
    {
        throw new LogicException('This code should never be reached because the route is handled by the security firewall.');
    }
}
