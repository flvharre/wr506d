<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Xylis\FakerCinema\Provider\Movie as MovieProvider;

class CategoryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();
        $faker->addProvider(new MovieProvider($faker));

        /**
         * @var \Faker\Generator $faker
         * @property string $movieGenre
         */
        $fakerWithCinema = $faker;

        $categoriesCreated = [];

        for ($i = 0; $i < 50; $i++) {
            $categoryName = $fakerWithCinema->movieGenre;

            if (!array_key_exists($categoryName, $categoriesCreated)) {
                $category = new Category();
                $category->setName($categoryName);
                $manager->persist($category);

                $categoriesCreated[$categoryName] = $category;
            }
        }

        $manager->flush();
    }
}
