<?php


namespace App\State;

use App\Entity\Movie;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class MovieCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security           $security,
        private readonly ProcessorInterface $persistProcessor
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // On vérifie que c'est bien un Movie en création
        if (!$data instanceof Movie) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        // Récupération de l'utilisateur connecté
        $user = $this->security->getUser();

        if (!$user instanceof \App\Entity\User) {
            throw new \RuntimeException('Vous devez être connecté pour créer un film');
        }

        // On associe automatiquement le créateur
        $data->setCreatedBy($user);

        // On délègue la persistance
        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
