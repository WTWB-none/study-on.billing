<?php

namespace App\Controller;

use LogicException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TokenRefreshController
{
    #[OA\Post(
        path: '/api/v1/token/refresh',
        tags: ['Auth'],
        summary: 'Refresh JWT token pair',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['refresh_token'],
                    properties: [
                        new OA\Property(property: 'refresh_token', type: 'string'),
                    ],
                    type: 'object'
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'JWT token refreshed',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string'),
                        new OA\Property(property: 'refresh_token', type: 'string'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Invalid refresh token'),
        ]
    )]
    #[Route('/api/v1/token/refresh', name: 'api_v1_token_refresh', methods: ['POST'])]
    public function __invoke(): Response
    {
        throw new LogicException('This code should never be reached because the route is handled by the security firewall.');
    }
}
