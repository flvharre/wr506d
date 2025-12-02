<?php

namespace App\DataFixtures;

use App\Entity\Actor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Xylis\FakerCinema\Provider\Person;

class ActorFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();
        $faker->addProvider(new Person($faker));

        /**
         * @var \Faker\Generator $faker
         * @method array actors(?string $gender = null, int $quantity = 10, bool $duplicates = true)
         */
        $fakerWithCinema = $faker;

        // Génération des acteurs via FakerCinema
        $actors = $fakerWithCinema->actors(null, 50, false);

        foreach ($actors as $i => $item) {
            $actor = new Actor();
            $parts = explode(' ', $item);

            $actor->setLastname($parts[0]);
            $actor->setFirstname($parts[1] ?? null);

            $dob = $faker->dateTimeBetween('-85 years', '-18 years');
            $actor->setDob($dob);

            if ($faker->boolean(30)) {
                $dod = $faker->dateTimeBetween($dob, 'now');
                $actor->setDod($dod);
            }

            $actor->setBio($faker->text(300));

            $manager->persist($actor);
            $this->addReference('actor_' . $i, $actor);
        }

        $manager->flush();
    }
}
