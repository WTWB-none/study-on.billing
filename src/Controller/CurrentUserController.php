<?php

namespace App\Controller;

use App\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CurrentUserController extends AbstractController
{
    #[OA\Get(
        tags: ['Users'],
        summary: 'Get current authenticated user',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current authenticated user',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'username', type: 'string', example: 'developer@intaro.ru'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_USER']),
                        new OA\Property(property: 'balance', type: 'number', format: 'float', example: 4741.1),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'JWT token is missing or invalid'),
        ]
    )]
    #[Route('/api/v1/users/current', name: 'api_v1_users_current', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'message' => 'User not found.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'username' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
            'balance' => $user->getBalance(),
        ]);
    }
}
