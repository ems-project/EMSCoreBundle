<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security\Authenticator;

use EMS\CoreBundle\Entity\UserInterface as CoreUserInterface;
use EMS\CoreBundle\Repository\AuthTokenRepository;
use EMS\CoreBundle\Routes;
use EMS\Helpers\Standard\Json;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;

final class AuthTokenLoginAuthenticator extends AbstractGuardAuthenticator
{
    private AuthTokenRepository $authTokenRepository;
    private UserPasswordEncoderInterface $passwordEncoder;

    public function __construct(AuthTokenRepository $authTokenRepository, UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->authTokenRepository = $authTokenRepository;
        $this->passwordEncoder = $passwordEncoder;
    }

    public function supports(Request $request): bool
    {
        return Routes::AUTH_TOKEN_LOGIN === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    public function start(Request $request, AuthenticationException $authException = null): JsonResponse
    {
        $data = ['message' => 'Authentication Required'];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return array{username: ?string, password: ?string}
     */
    public function getCredentials(Request $request): array
    {
        $json = Json::decode($request->getContent());

        return [
            'username' => $json['username'] ?? null,
            'password' => $json['password'] ?? null,
        ];
    }

    public function getUser($credentials, UserProviderInterface $userProvider): UserInterface
    {
        return $userProvider->loadUserByUsername($credentials['username']);
    }

    public function checkCredentials($credentials, UserInterface $user): bool
    {
        if (null === $credentials['password'] || null === $credentials['username']) {
            return false;
        }

        return $this->passwordEncoder->isPasswordValid($user, $credentials['password']);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'acknowledged' => true,
            'error' => ['Unauthorized Error'],
        ], Response::HTTP_UNAUTHORIZED);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey): JsonResponse
    {
        $user = $token->getUser();
        if (!$user instanceof CoreUserInterface) {
            throw new \RuntimeException(\sprintf('User should be of type %s', CoreUserInterface::class));
        }

        return new JsonResponse([
            'acknowledged' => true,
            'authToken' => $this->authTokenRepository->create($user)->getValue(),
            'success' => true,
        ]);
    }

    public function supportsRememberMe(): bool
    {
        return false;
    }
}
