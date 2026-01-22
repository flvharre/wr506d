<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\TwoFactorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/2fa', name: 'api_2fa_')]
class TwoFactorController extends AbstractController
{
    public function __construct(
        private readonly TwoFactorService   $twoFactorService,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/setup', name: 'setup', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function setup(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(
                ['error' => 'User not found'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $secret = $this->twoFactorService->generateSecret();
        $user->setTwoFactorSecret($secret);
        $user->setTwoFactorEnabled(false);

        $this->entityManager->flush();

        $qrCodeDataUri = $this->twoFactorService->getQrCode($user);
        $provisioningUri = $this->twoFactorService->getProvisioningUri($user);

        return new JsonResponse([
            'secret' => $secret,
            'qr_code' => $qrCodeDataUri,
            'provisioning_uri' => $provisioningUri,
            'message' => 'Scan the QR code with your authenticator app ' .
                '(Google Authenticator, Authy, etc.) then call ' .
                '/api/2fa/enable with a code to activate 2FA.',
        ]);
    }

    #[Route('/enable', name: 'enable', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function enable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(
                ['error' => 'User not found'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $data = json_decode($request->getContent(), true);
        $code = $data['code'] ?? '';

        if (empty($code)) {
            return new JsonResponse(
                ['error' => 'Code is required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!$this->twoFactorService->verifyCode($user, $code)) {
            return new JsonResponse(
                ['error' => 'Invalid code'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $backupCodes = $this->twoFactorService->generateBackupCodes();
        $hashedBackupCodes = $this->twoFactorService->hashBackupCodes($backupCodes);

        $user->setTwoFactorEnabled(true);
        $user->setTwoFactorBackupCodes($hashedBackupCodes);

        $this->entityManager->flush();

        return new JsonResponse([
            'message' => '2FA enabled successfully',
            'backup_codes' => $backupCodes,
            'warning' => 'Save these backup codes in a safe place. ' .
                'They can be used to access your account if you lose ' .
                'your authenticator device.',
        ]);
    }

    #[Route('/disable', name: 'disable', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function disable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(
                ['error' => 'User not found'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $data = json_decode($request->getContent(), true);
        $code = $data['code'] ?? '';

        if (empty($code)) {
            return new JsonResponse(
                ['error' => 'Code is required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!$this->twoFactorService->verifyCode($user, $code)) {
            return new JsonResponse(
                ['error' => 'Invalid code'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $user->setTwoFactorEnabled(false);
        $user->setTwoFactorSecret(null);
        $user->setTwoFactorBackupCodes(null);

        $this->entityManager->flush();

        return new JsonResponse([
            'message' => '2FA disabled successfully',
        ]);
    }

    #[Route('/status', name: 'status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function status(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(
                ['error' => 'User not found'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return new JsonResponse([
            'enabled' => $user->isTwoFactorEnabled(),
            'has_secret' => $user->getTwoFactorSecret() !== null,
            'backup_codes_count' => $user->getTwoFactorBackupCodes()
                ? count($user->getTwoFactorBackupCodes())
                : 0,
        ]);
    }
}
