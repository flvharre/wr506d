<?php

namespace App\DataFixtures;

use App\Entity\Director;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Xylis\FakerCinema\Provider\Person;

class DirectorFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();
        $faker->addProvider(new Person($faker));

        /**
         * @var \Faker\Generator $faker
         * @method array directors(?string $gender = null, int $quantity = 10, bool $duplicates = true)
         */
        $fakerWithCinema = $faker;

        // Génération des réalisateurs via FakerCinema
        $directors = $fakerWithCinema->directors(null, 30, false);

        foreach ($directors as $i => $item) {
            $director = new Director();
            $parts = explode(' ', $item);

            $director->setLastname($parts[0]);
            $director->setFirstname($parts[1] ?? null);
            $director->setDob($faker->dateTimeBetween('-85 years', '-25 years'));

            $manager->persist($director);
            $this->addReference('director_' . $i, $director);
        }

        $manager->flush();
    }
}
