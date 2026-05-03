<?php

namespace App\Controller;

use App\Repository\CourseRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CourseDetailController
{
    public function __construct(
        private readonly CourseRepository $courseRepository,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/courses/{code}',
        tags: ['Courses'],
        summary: 'Get course by code',
        parameters: [
            new OA\Parameter(
                name: 'code',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string'),
                example: 'python-data-analysis'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Course details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'string', example: 'python-data-analysis'),
                        new OA\Property(property: 'type', type: 'string', enum: ['free', 'rent', 'buy'], example: 'rent'),
                        new OA\Property(property: 'price', type: 'string', example: '99.90', nullable: true),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Course not found'),
        ]
    )]
    #[Route('/api/v1/courses/{code}', name: 'api_v1_courses_detail', methods: ['GET'])]
    public function __invoke(string $code): JsonResponse
    {
        $course = $this->courseRepository->findOneBy(['code' => $code]);
        if ($course === null) {
            return new JsonResponse([
                'message' => 'Course not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = [
            'code' => $course->getCode(),
            'type' => $course->getTypeName(),
        ];

        if ($course->getPrice() !== null) {
            $data['price'] = number_format($course->getPrice(), 2, '.', '');
        }

        return new JsonResponse($data);
    }
}
