<?php

namespace App\DataFixtures;

use App\Entity\Movie;
use App\Entity\MediaObject;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MovieMediaFixtures extends Fixture implements DependentFixtureInterface
{
    private string $uploadDir;

    public function __construct(string $uploadDir)
    {
        $this->uploadDir = $uploadDir;
    }

    public function load(ObjectManager $manager): void
    {
        $movieRepository = $manager->getRepository(Movie::class);
        $movies = $movieRepository->findAll();

        if (empty($movies)) {
            echo "Aucun film trouvé dans la base de données\n";
            return;
        }

        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        $tempDir = sys_get_temp_dir() . '/movie_posters';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        echo "Téléchargement des images pour " . count($movies) . " films...\n";

        foreach ($movies as $index => $movie) {
            try {
                $imageUrl = sprintf('https://picsum.photos/seed/%d/400/600', $movie->getId());

                $imageContent = @file_get_contents($imageUrl);

                if ($imageContent === false) {
                    echo "Erreur lors du téléchargement de l'image pour le film #{$movie->getId()}\n";
                    continue;
                }

                $tempFilePath = $tempDir . '/poster_' . $movie->getId() . '.jpg';
                file_put_contents($tempFilePath, $imageContent);

                $uploadedFile = new UploadedFile(
                    $tempFilePath,
                    'poster_' . $movie->getId() . '.jpg',
                    'image/jpeg',
                    null,
                    true
                );

                $mediaObject = new MediaObject();
                $mediaObject->file = $uploadedFile;
                $mediaObject->setMovie($movie);

                $manager->persist($mediaObject);

                if (($index + 1) % 10 === 0) {
                    $manager->flush();
                    echo "Traité " . ($index + 1) . " films...\n";

                    gc_collect_cycles();
                }
            } catch (\Exception $e) {
                echo "Erreur pour le film #{$movie->getId()}: {$e->getMessage()}\n";
                continue;
            }
        }

        $manager->flush();

        array_map('unlink', glob($tempDir . '/*'));
        if (is_dir($tempDir)) {
            rmdir($tempDir);
        }

        echo "Images ajoutées avec succès pour " . count($movies) . " films\n";
    }

    public function getDependencies(): array
    {
        return [
            MovieFixtures::class,
        ];
    }
}
