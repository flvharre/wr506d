<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\TwoFactorService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class CustomAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly UserRepository              $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface    $jwtManager,
        private readonly TwoFactorService        $twoFactorService,
        private readonly EntityManagerInterface      $entityManager,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->getPathInfo() === '/auth' && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            throw new CustomUserMessageAuthenticationException('Invalid JSON');
        }

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $totpCode = $data['totp_code'] ?? null;

        if (!is_string($email) || !is_string($password)) {
            throw new CustomUserMessageAuthenticationException('Email and password must be strings');
        }

        if (empty($email) || empty($password)) {
            throw new CustomUserMessageAuthenticationException('Email and password are required');
        }

        $request->attributes->set('_auth_password', $password);
        $request->attributes->set('_auth_totp_code', $totpCode);

        return new SelfValidatingPassport(new UserBadge($email, function (string $userIdentifier) {
            $user = $this->userRepository->findOneBy(['email' => $userIdentifier]);
            if (!$user instanceof User) {
                throw new CustomUserMessageAuthenticationException('Invalid credentials');
            }
            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Invalid user'], Response::HTTP_UNAUTHORIZED);
        }

        $password = $request->attributes->get('_auth_password');
        if (!is_string($password)) {
            return new JsonResponse(['error' => 'Invalid password format'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isTwoFactorEnabled() && $user->getTwoFactorSecret() !== null) {
            $totpCode = $request->attributes->get('_auth_totp_code');

            if ($totpCode === null || $totpCode === '') {
                return new JsonResponse([
                    'status' => 'totp_required',
                    'message' => '2FA code required. Please provide totp_code in the request body.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            if (!is_string($totpCode)) {
                return new JsonResponse([
                    'error' => 'Invalid TOTP code format',
                ], Response::HTTP_UNAUTHORIZED);
            }

            $isValid = $this->twoFactorService->verifyCode($user, $totpCode);

            if (!$isValid) {
                $isBackupCode = $this->twoFactorService->verifyBackupCode($user, $totpCode);

                if ($isBackupCode) {
                    $this->twoFactorService->removeBackupCode($user, $totpCode);
                    $this->entityManager->flush();

                    $jwt = $this->jwtManager->create($user);
                    return new JsonResponse([
                        'token' => $jwt,
                        'message' => 'Backup code used successfully. Please generate new backup codes.',
                        'backup_codes_remaining' => count($user->getTwoFactorBackupCodes() ?? []),
                    ]);
                }

                return new JsonResponse([
                    'error' => 'Invalid 2FA code',
                ], Response::HTTP_UNAUTHORIZED);
            }
        }

        $jwt = $this->jwtManager->create($user);
        return new JsonResponse([
            'token' => $jwt,
        ]);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => $exception->getMessage(),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
