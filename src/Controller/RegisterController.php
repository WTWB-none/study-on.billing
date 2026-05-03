<?php

namespace App\Controller;

use App\Dto\RegisterUserDto;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PaymentService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\Exception\RuntimeException as SerializerRuntimeException;
use JMS\Serializer\SerializerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegisterController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthenticationSuccessHandler $authenticationSuccessHandler,
        private readonly PaymentService $paymentService,
        private readonly float $initialBalance,
    ) {
    }

    #[OA\Post(
        path: '/api/v1/register',
        tags: ['Register'],
        summary: 'Register user',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'developer@intaro.ru'),
                    new OA\Property(property: 'password', type: 'string', minLength: 6, example: 'user123'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'User registered and authenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string'),
                        new OA\Property(property: 'refresh_token', type: 'string'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string'), example: ['ROLE_USER']),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Validation failed'),
            new OA\Response(response: 409, description: 'User already exists'),
        ]
    )]
    #[Route('/api/v1/register', name: 'api_v1_register', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        try {
            /** @var RegisterUserDto $dto */
            $dto = $this->serializer->deserialize($request->getContent(), RegisterUserDto::class, 'json');
        } catch (SerializerRuntimeException) {
            return $this->jsonResponse([
                'message' => 'Invalid JSON payload.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $errors = [];

            foreach ($violations as $violation) {
                $errors[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                ];
            }

            return $this->jsonResponse([
                'message' => 'Validation failed.',
                'errors' => $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($this->userRepository->findOneBy(['email' => $dto->email]) instanceof User) {
            return $this->jsonResponse([
                'message' => 'User with this email already exists.',
                'errors' => [
                    [
                        'field' => 'email',
                        'message' => 'User with this email already exists.',
                    ],
                ],
            ], Response::HTTP_CONFLICT);
        }

        $user = (new User())
            ->setEmail($dto->email)
            ->setRoles(['ROLE_USER']);

        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password));

        try {
            $this->entityManager->persist($user);
            $this->paymentService->deposit($user, $this->initialBalance);
        } catch (UniqueConstraintViolationException) {
            return $this->jsonResponse([
                'message' => 'User with this email already exists.',
                'errors' => [
                    [
                        'field' => 'email',
                        'message' => 'User with this email already exists.',
                    ],
                ],
            ], Response::HTTP_CONFLICT);
        }

        return $this->authenticationSuccessHandler->handleAuthenticationSuccess($user);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function jsonResponse(array $data, int $status): Response
    {
        return new Response(
            $this->serializer->serialize($data, 'json'),
            $status,
            ['Content-Type' => 'application/json']
        );
    }
}
