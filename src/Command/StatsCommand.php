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
            // 1. Argument obligatoire
            ->addArgument(
                'type',
                InputArgument::REQUIRED,
                'Type de statistique demandée : movies | actors | categories | images'
            )
            // 2. Option facultative sans valeur
            ->addOption(
                'detail',
                'd',
                InputOption::VALUE_NONE,
                'Affiche plus de détails (utilisé seulement pour les catégories)'
            )
            // 3. Option avec valeur obligatoire
            ->addOption(
                'log-file',
                null,
                InputOption::VALUE_REQUIRED,
                'Chemin vers le fichier où ajouter les résultats (ex: /tmp/stats.txt)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $type = $input->getArgument('type');
        $detail = $input->getOption('detail');
        $logFile = $input->getOption('log-file');

        // Préparation du contenu pour le log (avec timestamp)
        $timestamp = date('d/m/Y H:i:s');
        $logContent = $logFile ? "=== Statistiques du $timestamp ===\n\n" : "";

        // Fonction qui affiche à l'écran ET ajoute au log
        $write = function (string $message) use ($io, &$logContent, $logFile) {
            $io->writeln($message);
            if ($logFile) {
                $logContent .= $message . PHP_EOL;
            }
        };

        switch ($type) {
            case 'movies':
                $count = $this->movieRepository->count([]);
                $write("[OK] Nombre de films : $count");
                break;

            case 'actors':
                $count = $this->actorRepository->count([]);
                $write("[OK] Nombre d'acteurs : $count");
                break;

            case 'categories':
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
                break;

            case 'images':
                $mediaObjects = $this->mediaObjectRepository->findAll();
                $count = count($mediaObjects);
                $totalSize = 0;
                $filesystem = new Filesystem();

                $uploadDir = $this->projectDir . '/public/uploads/media';

                foreach ($mediaObjects as $media) {
                    if ($media->getFilePath()) {
                        $filePath = $uploadDir . '/' . $media->getFilePath();
                        if ($filesystem->exists($filePath)) {
                            $totalSize += filesize($filePath);
                        }
                    }
                }

                $totalSizeMo = round($totalSize / 1024 / 1024, 2);
                $totalSizeKo = round($totalSize / 1024, 2);

                $write("[OK] Nombre d'images : $count");
                if ($totalSizeMo >= 1) {
                    $write("[OK] Poids total occupé sur le serveur : {$totalSizeMo} Mo");
                } elseif ($totalSizeKo > 0) {
                    $write("[OK] Poids total occupé sur le serveur : {$totalSizeKo} Ko");
                } else {
                    $write("[OK] Poids total occupé sur le serveur : 0 Ko");
                }
                break;

            default:
                $write("<error>Type inconnu. Utilisez : movies, actors, categories ou images</error>");
                return Command::INVALID;
        }

        // Ajout au fichier de log (APPEND pour ne pas écraser les anciens)
        if ($logFile) {
            file_put_contents($logFile, $logContent . PHP_EOL, FILE_APPEND | LOCK_EX);
            $write("<info>Résultats ajoutés dans le fichier : $logFile</info>");
        }

        return Command::SUCCESS;
    }
}
