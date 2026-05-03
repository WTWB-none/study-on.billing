<?php

namespace App\Tests\Functional;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class TransactionControllerTest extends ApiTestCase
{
    public function testTransactionsRequireAuthentication(): void
    {
        $this->client->request('GET', '/api/v1/transactions');

        self::assertResponseStatusCodeSame(401);
    }

    public function testTransactionsListReturnsCurrentUserHistory(): void
    {
        $token = $this->authenticate();

        $this->client->request('GET', '/api/v1/transactions', server: [
            'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
        ]);

        $response = $this->client->getResponse();

        self::assertResponseIsSuccessful();
        self::assertJson($response->getContent());

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertCount(1, $payload);
        self::assertSame('deposit', $payload[0]['type']);
        self::assertSame('5000.00', $payload[0]['amount']);
        self::assertArrayNotHasKey('course_code', $payload[0]);
    }

    public function testTransactionsSupportFilters(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'user@example.com']);
        $course = $entityManager->getRepository(Course::class)->findOneBy(['code' => 'python-data-analysis']);

        self::assertInstanceOf(User::class, $user);
        self::assertInstanceOf(Course::class, $course);

        $entityManager->persist(
            (new Transaction())
                ->setUser($user)
                ->setCourse($course)
                ->setType(Transaction::TYPE_PAYMENT)
                ->setValue(99.9)
                ->setCreatedAt(new \DateTimeImmutable('2025-01-02T10:00:00+00:00'))
                ->setExpiresAt(new \DateTimeImmutable('+7 days'))
        );

        $entityManager->persist(
            (new Transaction())
                ->setUser($user)
                ->setCourse($course)
                ->setType(Transaction::TYPE_PAYMENT)
                ->setValue(99.9)
                ->setCreatedAt(new \DateTimeImmutable('2024-01-02T10:00:00+00:00'))
                ->setExpiresAt(new \DateTimeImmutable('-1 day'))
        );

        $entityManager->flush();

        $token = $this->authenticate();

        $this->client->request('GET', '/api/v1/transactions?filter[type]=payment&filter[course_code]=python-data-analysis&filter[skip_expired]=1', server: [
            'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
        ]);

        $response = $this->client->getResponse();

        self::assertResponseIsSuccessful();
        self::assertJson($response->getContent());

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertCount(1, $payload);
        self::assertSame('payment', $payload[0]['type']);
        self::assertSame('python-data-analysis', $payload[0]['course_code']);
        self::assertSame('99.90', $payload[0]['amount']);
    }
}
