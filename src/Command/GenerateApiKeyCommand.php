<?php

namespace App\Command;

use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-api-key',
    description: 'Generate an API key for a specific user',
)]
class GenerateApiKeyCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'The email of the user');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error(sprintf('User with email "%s" not found.', $email));
            return Command::FAILURE;
        }

        // Generate random bytes
        $randomBytes = random_bytes(32);

        // Convert to hex
        $apiKey = bin2hex($randomBytes);

        // Hash the key for storage
        $apiKeyHash = hash('sha256', $apiKey);

        // Extract prefix (first 16 chars of the key)
        $apiKeyPrefix = substr($apiKey, 0, 16);

        // Store hash and metadata
        $user->setApiKeyHash($apiKeyHash);
        $user->setApiKeyPrefix($apiKeyPrefix);
        $user->setApiKeyEnabled(true);
        $user->setApiKeyCreatedAt(new DateTimeImmutable());
        $user->setApiKeyLastUsedAt(null);

        $this->entityManager->flush();

        $io->success('API Key generated successfully!');
        $io->section('User Information');
        $io->table(
            ['Field', 'Value'],
            [
                ['Username', $user->getUsername()],
                ['Email', $user->getEmail()],
            ]
        );

        $io->section('API Key Details');
        $io->warning('IMPORTANT: Copy this API key now. It will never be shown again!');
        $io->table(
            ['Field', 'Value'],
            [
                ['API Key', $apiKey],
                ['Prefix', $apiKeyPrefix],
                ['Enabled', $user->isApiKeyEnabled() ? 'Yes' : 'No'],
                ['Created At', $user->getApiKeyCreatedAt()->format('Y-m-d H:i:s')],
            ]
        );

        return Command::SUCCESS;
    }
}
