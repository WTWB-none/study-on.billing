<?php

namespace App\Controller;

use App\Repository\CourseRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class CourseListController
{
    public function __construct(
        private readonly CourseRepository $courseRepository,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/courses',
        tags: ['Courses'],
        summary: 'Get list of courses',
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of courses',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'code', type: 'string', example: 'python-data-analysis'),
                            new OA\Property(property: 'type', type: 'string', enum: ['free', 'rent', 'buy'], example: 'rent'),
                            new OA\Property(property: 'price', type: 'string', example: '99.90', nullable: true),
                        ],
                        type: 'object'
                    )
                )
            ),
        ]
    )]
    #[Route('/api/v1/courses', name: 'api_v1_courses_list', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $courses = $this->courseRepository->findBy([], ['id' => 'ASC']);

        return new JsonResponse(array_map(
            static fn ($course) => self::mapCourse($course),
            $courses
        ));
    }

    private static function mapCourse(\App\Entity\Course $course): array
    {
        $data = [
            'code' => $course->getCode(),
            'type' => $course->getTypeName(),
        ];

        if ($course->getPrice() !== null) {
            $data['price'] = number_format($course->getPrice(), 2, '.', '');
        }

        return $data;
    }
}
