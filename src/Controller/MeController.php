<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\User;

class MeController extends AbstractController
{
    #[Route('/api/me', name: 'get_current_user', methods: ['GET'])]
    /**
     * @return JsonResponse
     */
    public function getCurrentUser(): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'id'       => $user->getId(),
            'email'    => $user->getEmail(),
            'username' => $user->getUsername(),
            'roles'    => $user->getRoles(),
        ]);
    }
}
