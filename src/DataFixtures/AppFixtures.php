<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        foreach ($this->getUsers() as $user) {
            $manager->persist($user);
        }

        $manager->flush();
    }

    /**
     * @return list<User>
     */
    private function getUsers(): array
    {
        $user = (new User())
            ->setEmail('user@example.com')
            ->setRoles(['ROLE_USER'])
            ->setBalance(0.0);

        $user->setPassword($this->passwordHasher->hashPassword($user, 'user123'));

        $superAdmin = (new User())
            ->setEmail('super-admin@example.com')
            ->setRoles(['ROLE_SUPER_ADMIN'])
            ->setBalance(0.0);

        $superAdmin->setPassword($this->passwordHasher->hashPassword($superAdmin, 'super-admin123'));

        return [$user, $superAdmin];
    }
}
