<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CourseControllerTest extends ApiTestCase
{
    public function testCourseListIsPublic(): void
    {
        $this->client->request('GET', '/api/v1/courses');

        $response = $this->client->getResponse();

        self::assertResponseIsSuccessful();
        self::assertJson($response->getContent());

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertCount(4, $payload);
        self::assertSame('python-data-analysis', $payload[0]['code']);
        self::assertSame('rent', $payload[0]['type']);
        self::assertSame('99.90', $payload[0]['price']);
        self::assertSame('ux-writing-basics', $payload[1]['code']);
        self::assertSame('free', $payload[1]['type']);
        self::assertArrayNotHasKey('price', $payload[1]);
    }

    public function testCourseDetailIsPublic(): void
    {
        $this->client->request('GET', '/api/v1/courses/sql-for-product-managers');

        $response = $this->client->getResponse();

        self::assertResponseIsSuccessful();
        self::assertJson($response->getContent());

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('sql-for-product-managers', $payload['code']);
        self::assertSame('buy', $payload['type']);
        self::assertSame('159.00', $payload['price']);
    }

    public function testCoursePayRequiresAuthentication(): void
    {
        $this->client->request('POST', '/api/v1/courses/python-data-analysis/pay');

        self::assertResponseStatusCodeSame(401);
    }

    public function testCanPayRentCourse(): void
    {
        $token = $this->authenticate();

        $this->client->request('POST', '/api/v1/courses/python-data-analysis/pay', server: [
            'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
        ]);

        $response = $this->client->getResponse();

        self::assertResponseIsSuccessful();
        self::assertJson($response->getContent());

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($payload['success']);
        self::assertSame('rent', $payload['course_type']);
        self::assertArrayHasKey('expires_at', $payload);

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['email' => 'user@example.com']);

        self::assertInstanceOf(User::class, $user);
        self::assertSame(4900.1, $user->getBalance());
    }

    public function testPayReturnsNotAcceptableWhenBalanceIsInsufficient(): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'user@example.com']);

        self::assertInstanceOf(User::class, $user);
        $user->setBalance(10.0);
        $entityManager->flush();

        $token = $this->authenticate();

        $this->client->request('POST', '/api/v1/courses/project-management-essentials/pay', server: [
            'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
        ]);

        $response = $this->client->getResponse();

        self::assertResponseStatusCodeSame(406);
        self::assertJson($response->getContent());

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(406, $payload['code']);
        self::assertSame('На вашем счету недостаточно средств', $payload['message']);
    }
}
