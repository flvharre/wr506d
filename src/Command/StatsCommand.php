<?php

namespace App\Command;

use App\Repository\ActorRepository;
use App\Repository\CategoryRepository;
use App\Repository\MediaObjectRepository;
use App\Repository\MovieRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'app:stats',
    description: 'Affiche les statistiques du catalogue de films',
)]
class StatsCommand extends Command
{
    public function __construct(
        private readonly MovieRepository $movieRepository,
        private readonly ActorRepository $actorRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly MediaObjectRepository $mediaObjectRepository,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('type', InputArgument::REQUIRED)
            ->addOption('detail', 'd', InputOption::VALUE_NONE)
            ->addOption('log-file', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $type = $input->getArgument('type');
        $detail = $input->getOption('detail');
        $logFile = $input->getOption('log-file');

        $timestamp = date('d/m/Y H:i:s');
        $logContent = $logFile ? "=== Statistiques du $timestamp ===\n\n" : "";

        $write = function (string $message) use ($io, &$logContent, $logFile) {
            $io->writeln($message);

            if (! $logFile) {
                return;
            }

            $logContent .= $message . PHP_EOL;
        };

        match ($type) {
            'movies'     => $this->statsMovies($write),
            'actors'     => $this->statsActors($write),
            'categories' => $this->statsCategories($write, $detail),
            'images'     => $this->statsImages($write),
            default      => $write("<error>Type inconnu : $type</error>"),
        };

        if ($logFile) {
            file_put_contents($logFile, $logContent . PHP_EOL, FILE_APPEND | LOCK_EX);
            $write("<info>Résultats ajoutés dans le fichier : $logFile</info>");
        }

        return Command::SUCCESS;
    }

    private function statsMovies(callable $write): void
    {
        $count = $this->movieRepository->count([]);
        $write("[OK] Nombre de films : $count");
    }

    private function statsActors(callable $write): void
    {
        $count = $this->actorRepository->count([]);
        $write("[OK] Nombre d'acteurs : $count");
    }

    private function statsCategories(callable $write, bool $detail): void
    {
        $categories = $this->categoryRepository->findAll();
        $totalMoviesInCategories = 0;

        $write("<info>=== Statistiques par catégorie ===</info>");
        $write("");

        foreach ($categories as $category) {
            $movieCount = $category->getMovies()->count();
            $totalMoviesInCategories += $movieCount;

            $line = "• {$category->getName()} : {$movieCount} film(s)";

            if ($detail) {
                $line .= " (id: {$category->getId()})";
            }

            $write($line);
        }

        $write("");
        $write("[OK] Total catégories : " . count($categories));
        $write("[OK] Total films dans les catégories : $totalMoviesInCategories");
    }

    private function statsImages(callable $write): void
    {
        $mediaObjects = $this->mediaObjectRepository->findAll();
        $filesystem = new Filesystem();

        $count = count($mediaObjects);
        $totalSize = 0;
        $uploadDir = $this->projectDir . '/public/uploads/media';

        foreach ($mediaObjects as $media) {
            $file = $media->getFilePath();

            if (! $file) {
                continue;
            }

            $filePath = $uploadDir . '/' . $file;

            if (! $filesystem->exists($filePath)) {
                continue;
            }

            $totalSize += filesize($filePath);
        }

        $totalSizeMo = round($totalSize / 1024 / 1024, 2);
        $totalSizeKo = round($totalSize / 1024, 2);

        $write("[OK] Nombre d'images : $count");

        if ($totalSizeMo >= 1) {
            $write("[OK] Poids total occupé sur le serveur : {$totalSizeMo} Mo");
            return;
        }

        if ($totalSizeKo > 0) {
            $write("[OK] Poids total occupé sur le serveur : {$totalSizeKo} Ko");
            return;
        }

        $write("[OK] Poids total occupé sur le serveur : 0 Ko");
    }
}
