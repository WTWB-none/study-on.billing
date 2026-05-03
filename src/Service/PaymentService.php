<?php

namespace App\Service;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

final class PaymentService
{
    private const RENT_INTERVAL = 'P7D';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function deposit(User $user, float $amount): Transaction
    {
        /** @var Transaction $transaction */
        $transaction = $this->entityManager->wrapInTransaction(function () use ($user, $amount): Transaction {
            $this->entityManager->persist($user);

            $transaction = (new Transaction())
                ->setUser($user)
                ->setType(Transaction::TYPE_DEPOSIT)
                ->setValue($amount)
                ->setCreatedAt(new \DateTimeImmutable());

            $user->setBalance($user->getBalance() + $amount);

            $this->entityManager->persist($transaction);
            $this->entityManager->flush();

            return $transaction;
        });

        return $transaction;
    }

    public function payForCourse(User $user, Course $course): Transaction
    {
        $price = $course->getPrice() ?? 0.0;

        if ($user->getBalance() < $price) {
            throw new NotAcceptableHttpException('На вашем счету недостаточно средств');
        }

        /** @var Transaction $transaction */
        $transaction = $this->entityManager->wrapInTransaction(function () use ($user, $course, $price): Transaction {
            $transaction = (new Transaction())
                ->setUser($user)
                ->setCourse($course)
                ->setType(Transaction::TYPE_PAYMENT)
                ->setValue($price)
                ->setCreatedAt(new \DateTimeImmutable());

            if ($course->getType() === Course::TYPE_RENT) {
                $transaction->setExpiresAt(
                    (new \DateTimeImmutable())->add(new \DateInterval(self::RENT_INTERVAL))
                );
            }

            $user->setBalance($user->getBalance() - $price);

            $this->entityManager->persist($transaction);
            $this->entityManager->flush();

            return $transaction;
        });

        return $transaction;
    }
}
