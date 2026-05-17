<?php

namespace App\Controller;

use App\Dto\UpsertCourseDto;
use App\Entity\Course;
use App\Repository\CourseRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\Exception\RuntimeException as SerializerRuntimeException;
use JMS\Serializer\SerializerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CourseCreateController extends AbstractController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly CourseRepository $courseRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[OA\Post(
        path: '/api/v1/courses',
        tags: ['Courses'],
        summary: 'Create course',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'title', 'code'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['free', 'rent', 'buy'], example: 'rent'),
                    new OA\Property(property: 'title', type: 'string', example: 'Python для анализа данных'),
                    new OA\Property(property: 'code', type: 'string', example: 'python-data-analysis'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 99.9, nullable: true),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Course created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Validation failed'),
            new OA\Response(response: 401, description: 'JWT token is missing or invalid'),
            new OA\Response(response: 403, description: 'Administrator role required'),
            new OA\Response(response: 409, description: 'Course code already exists'),
        ]
    )]
    #[Route('/api/v1/courses', name: 'api_v1_courses_create', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        try {
            /** @var UpsertCourseDto $dto */
            $dto = $this->serializer->deserialize($request->getContent(), UpsertCourseDto::class, 'json');
        } catch (SerializerRuntimeException) {
            return $this->jsonResponse([
                'message' => 'Invalid JSON payload.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $validationResponse = $this->validateDto($dto);
        if ($validationResponse !== null) {
            return $validationResponse;
        }

        if ($this->courseRepository->findOneBy(['code' => $dto->code]) instanceof Course) {
            return $this->codeConflictResponse();
        }

        $course = (new Course())
            ->setCode($dto->code)
            ->setTitle($dto->title)
            ->setType(Course::typeFromName($dto->type))
            ->setPrice($dto->type === 'free' ? null : $dto->price);

        try {
            $this->entityManager->persist($course);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->codeConflictResponse();
        }

        return $this->jsonResponse([
            'success' => true,
        ], Response::HTTP_CREATED);
    }

    private function validateDto(UpsertCourseDto $dto): ?Response
    {
        $violations = $this->validator->validate($dto);
        if (count($violations) === 0) {
            return null;
        }

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

    private function codeConflictResponse(): Response
    {
        return $this->jsonResponse([
            'message' => 'Course with this code already exists.',
            'errors' => [
                [
                    'field' => 'code',
                    'message' => 'Course with this code already exists.',
                ],
            ],
        ], Response::HTTP_CONFLICT);
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
