<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\TransactionRepository;
use Doctrine\ORM\QueryBuilder;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TransactionListController extends AbstractController
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/transactions',
        tags: ['Transactions'],
        summary: 'Get current user transaction history',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'filter[type]',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['payment', 'deposit']),
                example: 'payment'
            ),
            new OA\Parameter(
                name: 'filter[course_code]',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string'),
                example: 'python-data-analysis'
            ),
            new OA\Parameter(
                name: 'filter[skip_expired]',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean'),
                example: true
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User transactions',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 11),
                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2019-05-20T13:46:07+00:00'),
                            new OA\Property(property: 'type', type: 'string', enum: ['payment', 'deposit'], example: 'payment'),
                            new OA\Property(property: 'course_code', type: 'string', example: 'python-data-analysis', nullable: true),
                            new OA\Property(property: 'amount', type: 'string', example: '159.00'),
                        ],
                        type: 'object'
                    )
                )
            ),
            new OA\Response(response: 401, description: 'JWT token is missing or invalid'),
        ]
    )]
    #[Route('/api/v1/transactions', name: 'api_v1_transactions_list', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([
                'message' => 'User not found.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $queryBuilder = $this->transactionRepository->createQueryBuilder('t')
            ->leftJoin('t.course', 'c')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC');

        $filter = $request->query->all('filter');

        $type = $filter['type'] ?? null;
        if (is_string($type)) {
            $transactionType = match ($type) {
                'deposit' => Transaction::TYPE_DEPOSIT,
                'payment' => Transaction::TYPE_PAYMENT,
                default => null,
            };

            if ($transactionType !== null) {
                $queryBuilder
                    ->andWhere('t.type = :type')
                    ->setParameter('type', $transactionType);
            }
        }

        $courseCode = $filter['course_code'] ?? null;
        if (is_string($courseCode) && $courseCode !== '') {
            $queryBuilder
                ->andWhere('c.code = :courseCode')
                ->setParameter('courseCode', $courseCode);
        }

        $skipExpired = filter_var($filter['skip_expired'] ?? false, FILTER_VALIDATE_BOOL);
        if ($skipExpired) {
            $queryBuilder
                ->andWhere('t.expiresAt IS NULL OR t.expiresAt > :now')
                ->setParameter('now', new \DateTimeImmutable());
        }

        $transactions = $queryBuilder->getQuery()->getResult();

        return new JsonResponse(array_map(
            static fn (Transaction $transaction) => self::mapTransaction($transaction),
            $transactions
        ));
    }

    private static function mapTransaction(Transaction $transaction): array
    {
        $data = [
            'id' => $transaction->getId(),
            'created_at' => $transaction->getCreatedAt()?->format(DATE_ATOM),
            'type' => $transaction->getTypeName(),
            'amount' => number_format($transaction->getValue() ?? 0.0, 2, '.', ''),
        ];

        if ($transaction->getCourse() !== null) {
            $data['course_code'] = $transaction->getCourse()?->getCode();
        }

        return $data;
    }
}
