<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Operation;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AppFixtures extends Fixture
{
    private $passwordEncoder;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    /**
     * create admin account and 10 users owning 10 transactions each.
     */
    public function load(ObjectManager $manager)
    {
        // create admin account
        $user = new User();
        $user->setEmail('mat@tangoman.io');
        $user->setPassword(
            $this->passwordEncoder->encodePassword(
                $user,
                'tango'
            )
        );
        $user->setRoles(['ROLE_ADMIN']);

        $manager->persist($user);
        $manager->flush();

        $faker = Factory::create();

        for ($i = 0; $i < 5; ++$i) {
            $user = new User();
            $user->setEmail($faker->email);
            $user->setPassword(
                $this->passwordEncoder->encodePassword(
                    $user,
                    $faker->uuid
                )
            );
            $user->setRoles(['ROLE_USER']);

            for ($j = 0; $j < 5; ++$j) {
                $operation = new Operation();
                $operation->setName($faker->word);
                $operation->setDateCreated($faker->dateTimeBetween('-10 Days'));
                $operation->setOwner($user);

                $user->addOperation($operation);
                $manager->persist($operation);
            }

            $manager->persist($user);
            $manager->flush();
        }
    }
}
