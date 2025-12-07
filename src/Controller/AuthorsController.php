<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class AuthorsController extends AbstractController
{
    #[Route('/api/authors', name: 'api_authors', methods: ['GET'])]
    public function getAuthors(UserRepository $userRepository): JsonResponse
    {
        // Récupérer uniquement les utilisateurs qui ont créé au moins un film
        $qb = $userRepository->createQueryBuilder('u')
            ->select('u.id', 'u.username')
            ->innerJoin('u.movies', 'm')
            ->groupBy('u.id')
            ->orderBy('u.username', 'ASC');

        $authors = $qb->getQuery()->getResult();

        return $this->json($authors);
    }
}
