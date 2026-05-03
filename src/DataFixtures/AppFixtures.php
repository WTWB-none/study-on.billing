<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\User;
use App\Service\PaymentService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PaymentService $paymentService,
        private readonly float $initialBalance,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        [$users, $courses] = $this->getFixtures();

        foreach ($users as $user) {
            $manager->persist($user);
        }

        foreach ($courses as $course) {
            $manager->persist($course);
        }

        $manager->flush();

        foreach ($users as $user) {
            if ($user->getEmail() === 'user@example.com') {
                $this->paymentService->deposit($user, 10.0);
                continue;
            }

            $this->paymentService->deposit($user, $this->initialBalance);
        }
    }

    /**
     * @return array{list<User>, list<Course>}
     */
    private function getFixtures(): array
    {
        $user = (new User())
            ->setEmail('user@example.com')
            ->setRoles(['ROLE_USER']);

        $user->setPassword($this->passwordHasher->hashPassword($user, 'user123'));

        $superAdmin = (new User())
            ->setEmail('super-admin@example.com')
            ->setRoles(['ROLE_SUPER_ADMIN']);

        $superAdmin->setPassword($this->passwordHasher->hashPassword($superAdmin, 'super-admin123'));

        $rentCourse = (new Course())
            ->setCode('python-data-analysis')
            ->setType(Course::TYPE_RENT)
            ->setPrice(99.9);

        $freeCourse = (new Course())
            ->setCode('ux-writing-basics')
            ->setType(Course::TYPE_FREE)
            ->setPrice(null);

        $buyCourse = (new Course())
            ->setCode('sql-for-product-managers')
            ->setType(Course::TYPE_BUY)
            ->setPrice(159.0);

        $secondBuyCourse = (new Course())
            ->setCode('project-management-essentials')
            ->setType(Course::TYPE_BUY)
            ->setPrice(199.0);

        return [
            [$user, $superAdmin],
            [$rentCourse, $freeCourse, $buyCourse, $secondBuyCourse],
        ];
    }
}
