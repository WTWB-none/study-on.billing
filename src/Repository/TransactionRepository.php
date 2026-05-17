<?php

namespace App\Repository;

use App\Entity\Course;
use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * @return list<Transaction>
     */
    public function findRentTransactionsExpiringBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('t')
            ->addSelect('u', 'c')
            ->innerJoin('t.user', 'u')
            ->innerJoin('t.course', 'c')
            ->andWhere('t.type = :transactionType')
            ->andWhere('c.type = :courseType')
            ->andWhere('t.expiresAt IS NOT NULL')
            ->andWhere('t.expiresAt >= :from')
            ->andWhere('t.expiresAt < :to')
            ->setParameter('transactionType', Transaction::TYPE_PAYMENT)
            ->setParameter('courseType', Course::TYPE_RENT)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('u.email', 'ASC')
            ->addOrderBy('t.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<array{
     *     title: string,
     *     courseType: int,
     *     paymentsCount: int|string,
     *     totalAmount: float|string
     * }>
     */
    public function getPaidCoursesReport(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var list<array{title: string, courseType: int, paymentsCount: int|string, totalAmount: float|string}> $result */
        $result = $this->createQueryBuilder('t')
            ->select('c.title AS title')
            ->addSelect('c.type AS courseType')
            ->addSelect('COUNT(t.id) AS paymentsCount')
            ->addSelect('SUM(t.value) AS totalAmount')
            ->innerJoin('t.course', 'c')
            ->andWhere('t.type = :transactionType')
            ->andWhere('t.createdAt >= :from')
            ->andWhere('t.createdAt < :to')
            ->groupBy('c.id', 'c.title', 'c.type')
            ->orderBy('c.title', 'ASC')
            ->setParameter('transactionType', Transaction::TYPE_PAYMENT)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getArrayResult();

        return $result;
    }
}
