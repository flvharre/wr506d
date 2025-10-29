<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MeController extends AbstractController
{
    #[Route('/api/me', name: 'get_current_user', methods: ['GET'])]
    public function getCurrentUser(UserInterface $user): JsonResponse
    {
        // Vérification de l'utilisateur (sécurité double-check)
        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non authentifié'], 401);
        }

        // Préparer les données à exposer
        $userData = [
            'email' => $user->getEmail(),
            'roles' => $user->getRoles()
        ];

        return new JsonResponse($userData);
    }
}
