<?php

// src/State/CommentCreateProcessor.php

namespace App\State;

use App\Entity\Comment;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class CommentCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ProcessorInterface $persistProcessor
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // On vérifie que c'est bien un Comment
        if (!$data instanceof Comment) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        // Récupération de l'utilisateur connecté
        $user = $this->security->getUser();

        if (!$user instanceof \App\Entity\User) {
            throw new \RuntimeException('Vous devez être connecté pour commenter');
        }

        // On associe automatiquement l'auteur
        $data->setAuthor($user);

        // On délègue la persistance au processor par défaut de Doctrine
        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
