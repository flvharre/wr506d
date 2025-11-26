<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory as FakerFactory;
use DateTimeImmutable;
use App\Entity\Actor;
use App\Entity\Category;
use App\Entity\Movie;
use App\Entity\Director;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = FakerFactory::create();
        $faker->addProvider(new \Xylis\FakerCinema\Provider\Person($faker));

        // ========================================
        // 1. CRÉATION DES ACTEURS
        // ========================================
        $actors = $faker->actors(null, 50, false);
        $actorsCreated = [];

        foreach ($actors as $item) {
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
            $actorsCreated[] = $actor;
        }

        // ========================================
        // 2. CRÉATION DES RÉALISATEURS
        // ========================================
        $directors = $faker->directors(null, 30, false);
        $directorsCreated = [];

        foreach ($directors as $item) {
            $director = new Director();
            $parts = explode(' ', $item);

            $director->setLastname($parts[0]);
            $director->setFirstname($parts[1] ?? null);
            $director->setDob($faker->dateTimeBetween('-85 years', '-25 years'));

            $manager->persist($director);
            $directorsCreated[] = $director;
        }

        // ========================================
        // 3. GÉNÉRATION DE TOUTES LES CATÉGORIES D'ABORD
        // ========================================
        $faker->addProvider(new \Xylis\FakerCinema\Provider\Movie($faker));

        $categoriesCreated = [];

        // Générer environ 20 catégories uniques via Faker
        for ($i = 0; $i < 50; $i++) {
            $categoryName = $faker->movieGenre;

            // Éviter les doublons
            if (!array_key_exists($categoryName, $categoriesCreated)) {
                $category = new Category();
                $category->setName($categoryName);
                $manager->persist($category);

                $categoriesCreated[$categoryName] = $category;
            }
        }

        // ========================================
        // 4. CRÉATION DES FILMS
        // ========================================
        // 4. CRÉATION DES FILMS
        // ========================================
        $durationMin = 60; // 1 heure
        $durationMax = 270; // 4h30

        $movies = $faker->movies(199);

        // Convertir le tableau associatif en tableau indexé pour shuffle
        $categoriesArray = array_values($categoriesCreated);

        foreach ($movies as $item) {
            $movie = new Movie();
            $movie->setName($item);
            $movie->setDescription($faker->overview);
            $movie->setDuration($faker->numberBetween($durationMin, $durationMax));

            // ✅ CORRECTION : utiliser setReleaseDate() et non setReleased()
            $movie->setReleaseDate($faker->dateTimeBetween('-50 years', 'now'));

            $movie->setMetascore($faker->numberBetween(40, 99));
            $movie->setOnline(true);

            // Associer un réalisateur aléatoire
            $randomDirectorKey = array_rand($directorsCreated);
            $director = $directorsCreated[$randomDirectorKey];
            $movie->setDirector($director);

            // Associer 1 à 3 catégories aléatoires
            shuffle($categoriesArray);
            $nbCategories = rand(1, 3);
            $selectedCategories = array_slice($categoriesArray, 0, $nbCategories);

            foreach ($selectedCategories as $category) {
                $movie->addCategory($category);
            }

            // Associer 2 à 6 acteurs aléatoires
            shuffle($actorsCreated);
            $nbActors = rand(2, 6);
            $selectedActors = array_slice($actorsCreated, 0, $nbActors);

            foreach ($selectedActors as $actor) {
                $movie->addActor($actor);
            }

            $manager->persist($movie);
        }

        // ========================================
        // 5. FLUSH FINAL
        // ========================================
        $manager->flush();
    }
}
