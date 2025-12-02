<?php

namespace App\DataFixtures;

use App\Entity\Movie;
use App\Entity\Actor;
use App\Entity\Category;
use App\Entity\Director;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Xylis\FakerCinema\Provider\Movie as MovieProvider;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class MovieFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();
        $faker->addProvider(new MovieProvider($faker));

        /**
         * @var \Faker\Generator $faker
         * @method array movies(int $quantity = 10)
         * @property string $overview
         */
        $fakerWithCinema = $faker;

        // Charger acteurs, catégories, réalisateurs depuis les repositories
        $actorRepository = $manager->getRepository(Actor::class);
        $categoryRepository = $manager->getRepository(Category::class);
        $directorRepository = $manager->getRepository(Director::class);

        $actors = $actorRepository->findAll();
        $categories = $categoryRepository->findAll();
        $directors = $directorRepository->findAll();

        if (empty($actors)) {
            echo "Aucun acteur trouvé dans la base de données\n";
            return;
        }
        if (empty($categories)) {
            echo "Aucune catégorie trouvée dans la base de données\n";
            return;
        }
        if (empty($directors)) {
            echo "Aucun réalisateur trouvé dans la base de données\n";
            return;
        }

        // Paramètres de durée
        $durationMin = 60;  // 1 heure
        $durationMax = 270; // 4h30

        // Génération des films via FakerCinema
        $movies = $fakerWithCinema->movies(199);

        foreach ($movies as $item) {
            $movie = new Movie();
            $movie->setName($item);

            $movie->setDescription($fakerWithCinema->overview);
            $movie->setDuration($faker->numberBetween($durationMin, $durationMax));
            $movie->setReleaseDate($faker->dateTimeBetween('-50 years', 'now'));
            $movie->setMetascore($faker->numberBetween(40, 99));

            // Associer réalisateur
            $movie->setDirector($faker->randomElement($directors));

            // Associer catégories (1 à 3)
            $randomCategories = $faker->randomElements($categories, $faker->numberBetween(1, 3));
            foreach ($randomCategories as $category) {
                $movie->addCategory($category);
            }

            // Associer acteurs (2 à 6)
            $randomActors = $faker->randomElements($actors, $faker->numberBetween(2, 6));
            foreach ($randomActors as $actor) {
                $movie->addActor($actor);
            }

            $manager->persist($movie);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            ActorFixtures::class,
            CategoryFixtures::class,
            DirectorFixtures::class,
        ];
    }
}
