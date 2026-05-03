<?php

namespace App\Controller;

use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RememberMeAuthController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly RefreshTokenGeneratorInterface $refreshTokenGenerator,
        private readonly RefreshTokenManagerInterface $refreshTokenManager,
        private readonly string $internalAuthSecret,
        private readonly int $refreshTokenTtl,
    ) {
    }

    #[OA\Post(
        path: '/api/v1/auth/remember',
        tags: ['Auth'],
        summary: 'Issue JWT for trusted remember-me restore',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'developer@intaro.ru'),
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
                        new OA\Property(property: 'username', type: 'string'),
                        new OA\Property(property: 'token', type: 'string'),
                        new OA\Property(property: 'refresh_token', type: 'string'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                        new OA\Property(property: 'balance', type: 'number', format: 'float'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 403, description: 'Invalid internal auth secret'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    #[Route('/api/v1/auth/remember', name: 'api_v1_auth_remember', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->headers->get('X-Internal-Auth-Secret', '') !== $this->internalAuthSecret) {
            return new JsonResponse([
                'message' => 'Invalid internal auth secret.',
            ], Response::HTTP_FORBIDDEN);
        }

        $username = $request->getPayload()->getString('username');
        $user = $this->userRepository->findOneBy(['email' => $username]);

        if ($user === null) {
            return new JsonResponse([
                'message' => 'User not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl($user, $this->refreshTokenTtl);
        $this->refreshTokenManager->save($refreshToken);

        return new JsonResponse([
            'username' => $user->getUserIdentifier(),
            'token' => $this->jwtTokenManager->create($user),
            'refresh_token' => $refreshToken->getRefreshToken(),
            'roles' => $user->getRoles(),
            'balance' => $user->getBalance(),
        ]);
    }
}
