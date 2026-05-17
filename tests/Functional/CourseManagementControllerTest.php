<?php

namespace App\Tests\Functional;

use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;

final class CourseManagementControllerTest extends ApiTestCase
{
    public function testCreateCourseRequiresAuthentication(): void
    {
        $this->postJson('/api/v1/courses', [
            'type' => 'buy',
            'title' => 'Новый курс',
            'code' => 'new-course',
            'price' => 199.0,
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testCreateCourseRequiresAdministratorRole(): void
    {
        $token = $this->authenticate();

        $this->postJson('/api/v1/courses', [
            'type' => 'buy',
            'title' => 'Новый курс',
            'code' => 'new-course',
            'price' => 199.0,
        ], [
            'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanCreateCourse(): void
    {
        $token = $this->authenticate('super-admin@example.com', 'super-admin123');

        $this->postJson('/api/v1/courses', [
            'type' => 'buy',
            'title' => 'Тестирование Symfony приложений',
            'code' => 'php-testing-for-symfony',
            'price' => 199.0,
        ], [
            'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
        ]);

        $response = $this->client->getResponse();

        self::assertResponseStatusCodeSame(201);
        self::assertJson($response->getContent());
        self::assertSame(['success' => true], json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR));

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $course = $entityManager->getRepository(Course::class)->findOneBy(['code' => 'php-testing-for-symfony']);

        self::assertInstanceOf(Course::class, $course);
        self::assertSame('Тестирование Symfony приложений', $course->getTitle());
        self::assertSame(Course::TYPE_BUY, $course->getType());
        self::assertSame(199.0, $course->getPrice());
    }

    public function testAdminCannotCreateCourseWithDuplicateCode(): void
    {
        $token = $this->authenticate('super-admin@example.com', 'super-admin123');

        $this->postJson('/api/v1/courses', [
            'type' => 'rent',
            'title' => 'Дубликат курса',
            'code' => 'python-data-analysis',
            'price' => 99.9,
        ], [
            'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
        ]);

        $response = $this->client->getResponse();

        self::assertResponseStatusCodeSame(409);
        self::assertJson($response->getContent());

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('Course with this code already exists.', $payload['message']);
        self::assertSame('code', $payload['errors'][0]['field']);
    }

    public function testUpdateCourseRequiresAdministratorRole(): void
    {
        $token = $this->authenticate();

        $this->postJson('/api/v1/courses/python-data-analysis', [
            'type' => 'rent',
            'title' => 'Python для анализа данных 2.0',
            'code' => 'python-data-analysis-updated',
            'price' => 129.0,
        ], [
            'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanUpdateCourseAndChangeCode(): void
    {
        $token = $this->authenticate('super-admin@example.com', 'super-admin123');

        $this->postJson('/api/v1/courses/python-data-analysis', [
            'type' => 'rent',
            'title' => 'Python для анализа данных 2.0',
            'code' => 'python-data-analysis-updated',
            'price' => 129.0,
        ], [
            'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
        ]);

        $response = $this->client->getResponse();

        self::assertResponseIsSuccessful();
        self::assertJson($response->getContent());
        self::assertSame(['success' => true], json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR));

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $course = $entityManager->getRepository(Course::class)->findOneBy(['code' => 'python-data-analysis-updated']);

        self::assertInstanceOf(Course::class, $course);
        self::assertSame('Python для анализа данных 2.0', $course->getTitle());
        self::assertSame(129.0, $course->getPrice());
    }

    public function testAdminCannotUpdateCourseWithDuplicateCode(): void
    {
        $token = $this->authenticate('super-admin@example.com', 'super-admin123');

        $this->postJson('/api/v1/courses/python-data-analysis', [
            'type' => 'rent',
            'title' => 'Python для анализа данных',
            'code' => 'ux-writing-basics',
            'price' => 99.9,
        ], [
            'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
        ]);

        $response = $this->client->getResponse();

        self::assertResponseStatusCodeSame(409);
        self::assertJson($response->getContent());

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('Course with this code already exists.', $payload['message']);
        self::assertSame('code', $payload['errors'][0]['field']);
    }
}
