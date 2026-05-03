<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\User;
use App\Repository\CourseRepository;
use App\Service\PaymentService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CoursePayController extends AbstractController
{
    public function __construct(
        private readonly CourseRepository $courseRepository,
        private readonly PaymentService $paymentService,
    ) {
    }

    #[OA\Post(
        path: '/api/v1/courses/{code}/pay',
        tags: ['Courses'],
        summary: 'Pay for a course',
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
        responses: [
            new OA\Response(
                response: 200,
                description: 'Course paid successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'course_type', type: 'string', enum: ['rent', 'buy'], example: 'rent'),
                        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', example: '2019-05-20T13:46:07+00:00', nullable: true),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 406,
                description: 'Insufficient funds',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 406),
                        new OA\Property(property: 'message', type: 'string', example: 'На вашем счету недостаточно средств'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'JWT token is missing or invalid'),
            new OA\Response(response: 404, description: 'Course not found'),
        ]
    )]
    #[Route('/api/v1/courses/{code}/pay', name: 'api_v1_courses_pay', methods: ['POST'])]
    public function __invoke(string $code): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'message' => 'User not found.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $course = $this->courseRepository->findOneBy(['code' => $code]);
        if ($course === null) {
            return new JsonResponse([
                'message' => 'Course not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($course->getType() === Course::TYPE_FREE) {
            return new JsonResponse([
                'success' => true,
                'course_type' => $course->getTypeName(),
            ]);
        }

        try {
            $transaction = $this->paymentService->payForCourse($user, $course);
        } catch (NotAcceptableHttpException $exception) {
            return new JsonResponse([
                'code' => Response::HTTP_NOT_ACCEPTABLE,
                'message' => $exception->getMessage(),
            ], Response::HTTP_NOT_ACCEPTABLE);
        }

        $response = [
            'success' => true,
            'course_type' => $course->getTypeName(),
        ];

        if ($transaction->getExpiresAt() !== null) {
            $response['expires_at'] = $transaction->getExpiresAt()?->format(DATE_ATOM);
        }

        return new JsonResponse($response);
    }
}
