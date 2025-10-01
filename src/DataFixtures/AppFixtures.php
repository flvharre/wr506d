<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory as FakerFactory;
use DateTimeImmutable;
use App\Entity\Actor;
use App\Entity\Category;
use App\Entity\Movie;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = FakerFactory::create();
        $faker->addProvider(new \Xylis\FakerCinema\Provider\Person($faker));

        $actors = $faker->actors(null, 50, false); // 50 acteurs
        $actorsCreated = []; // stocke les acteurs créés

        foreach ($actors as $item) {
            $actor = new Actor();

            $parts = explode(' ', $item);

            $actor->setLastname($parts[0]);
            $actor->setFirstname($parts[1] ?? null);

            // Date de naissance : entre 18 et 85 ans
            $dob = $faker->dateTimeBetween('-85 years', '-18 years');
            $actor->setDob($dob);

            // Date de décès : 30% de chance, après la naissance
            if ($faker->boolean(30)) {
                $dod = $faker->dateTimeBetween($dob, 'now');
                $actor->setDod($dod);
            }

            // Bio aléatoire (~300 caractères)
            $actor->setBio($faker->text(300));

            // Photo aléatoire (400x600, catégorie "people") MAIS ne marche plus car obsolète
            $actor->setPhoto($faker->imageUrl(400, 600, 'people', true, 'actor'));

            $manager->persist($actor);

            $actorsCreated[] = $actor; // ajoute l’acteur au tableau
        }

        $faker->addProvider(new \Xylis\FakerCinema\Provider\Movie($faker));

        $durationMin = 60 * 60;
        $durationMax = 270 * 60;
        $categoriesCreated = [];

        $movies = $faker->movies(199);
        foreach ($movies as $item) {
            $movie = new Movie();
            $movie->setName($item);
            $movie->setDescription($faker->overview); // synopsis
            $movie->setDuration($faker->numberBetween($durationMin, $durationMax));
            $movie->setReleaseDate($faker->dateTimeBetween('-50 years', 'now'));

            // Gestion catégories
            $categoryName = $faker->movieGenre;
            if (!array_key_exists($categoryName, $categoriesCreated)) {
                $category = new Category();
                $category->setName($categoryName);
                $manager->persist($category);

                $categoriesCreated[$categoryName] = $category;
            } else {
                $category = $categoriesCreated[$categoryName];
            }

            $movie->addCategory($category);

            //Associer 2 à 6 acteurs
            shuffle($actorsCreated);
            $nbActors = rand(2, 6);
            $selectedActors = array_slice($actorsCreated, 0, $nbActors);

            foreach ($selectedActors as $actor) {
                $movie->addActor($actor);
            }

            $manager->persist($movie);
        }

        $manager->flush();
    }

}
