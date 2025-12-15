<?php

namespace App\Controller;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/me/api-key', name: 'api_key_')]
class ApiKeyController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'generate', methods: ['POST'])]
    #[IsGranted('ROLE_AUTHOR')]
    public function generate(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

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

        return new JsonResponse([
            'message' => 'API key generated successfully. Copy it now, it will not be shown again.',
            'api_key' => $apiKey,
            'prefix' => $apiKeyPrefix,
            'created_at' => $user->getApiKeyCreatedAt()->format('Y-m-d H:i:s'),
        ], Response::HTTP_CREATED);
    }

    #[Route('', name: 'status', methods: ['GET'])]
    #[IsGranted('ROLE_AUTHOR')]
    public function status(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->getApiKeyHash()) {
            return new JsonResponse([
                'message' => 'No API key configured',
                'has_key' => false,
            ]);
        }

        return new JsonResponse([
            'has_key' => true,
            'prefix' => $user->getApiKeyPrefix(),
            'enabled' => $user->isApiKeyEnabled(),
            'created_at' => $user->getApiKeyCreatedAt()?->format('Y-m-d H:i:s'),
            'last_used_at' => $user->getApiKeyLastUsedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('', name: 'toggle', methods: ['PATCH'])]
    #[IsGranted('ROLE_AUTHOR')]
    public function toggle(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->getApiKeyHash()) {
            return new JsonResponse([
                'message' => 'No API key configured',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $enabled = $data['enabled'] ?? null;

        if (null === $enabled || !is_bool($enabled)) {
            return new JsonResponse([
                'message' => 'Invalid request. Provide "enabled" boolean field.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user->setApiKeyEnabled($enabled);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => $enabled ? 'API key enabled' : 'API key disabled',
            'enabled' => $user->isApiKeyEnabled(),
        ]);
    }

    #[Route('', name: 'revoke', methods: ['DELETE'])]
    #[IsGranted('ROLE_AUTHOR')]
    public function revoke(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->getApiKeyHash()) {
            return new JsonResponse([
                'message' => 'No API key configured',
            ], Response::HTTP_NOT_FOUND);
        }

        $user->setApiKeyHash(null);
        $user->setApiKeyPrefix(null);
        $user->setApiKeyEnabled(false);
        $user->setApiKeyCreatedAt(null);
        $user->setApiKeyLastUsedAt(null);

        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'API key revoked successfully',
        ]);
    }
}
