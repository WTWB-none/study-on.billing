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

final class CourseUpdateController extends AbstractController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly CourseRepository $courseRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[OA\Post(
        path: '/api/v1/courses/{code}',
        tags: ['Courses'],
        summary: 'Update course',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'code',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string'),
                example: 'python-data-analysis'
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'title', 'code'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['free', 'rent', 'buy'], example: 'buy'),
                    new OA\Property(property: 'title', type: 'string', example: 'Python для анализа данных 2.0'),
                    new OA\Property(property: 'code', type: 'string', example: 'python-data-analysis-updated'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 199.0, nullable: true),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Course updated',
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
            new OA\Response(response: 404, description: 'Course not found'),
            new OA\Response(response: 409, description: 'Course code already exists'),
        ]
    )]
    #[Route('/api/v1/courses/{code}', name: 'api_v1_courses_update', methods: ['POST'])]
    public function __invoke(string $code, Request $request): Response
    {
        $course = $this->courseRepository->findOneBy(['code' => $code]);
        if (!$course instanceof Course) {
            return $this->jsonResponse([
                'message' => 'Course not found.',
            ], Response::HTTP_NOT_FOUND);
        }

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

        $existingCourse = $this->courseRepository->findOneBy(['code' => $dto->code]);
        if ($existingCourse instanceof Course && $existingCourse->getId() !== $course->getId()) {
            return $this->codeConflictResponse();
        }

        $course
            ->setCode($dto->code)
            ->setTitle($dto->title)
            ->setType(Course::typeFromName($dto->type))
            ->setPrice($dto->type === 'free' ? null : $dto->price);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->codeConflictResponse();
        }

        return $this->jsonResponse([
            'success' => true,
        ], Response::HTTP_OK);
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
