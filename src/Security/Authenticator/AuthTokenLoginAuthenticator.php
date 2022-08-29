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
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

final class AuthTokenLoginAuthenticator extends AbstractAuthenticator
{
    private AuthTokenRepository $authTokenRepository;

    public function __construct(AuthTokenRepository $authTokenRepository)
    {
        $this->authTokenRepository = $authTokenRepository;
    }

    public function supports(Request $request): bool
    {
        return Routes::AUTH_TOKEN_LOGIN === $request->attributes->get('_route') && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $content = $request->getContent();
        $json = $content ? Json::decode($content) : [];

        $username = $json['username'] ?? null;
        $password = $json['password'] ?? null;

        if (null === $username || null === $password) {
            throw new AuthenticationException('Missing credentials');
        }

        return new Passport(new UserBadge($username), new PasswordCredentials($password));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
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

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'success' => false,
            'acknowledged' => true,
            'error' => 'Login authentication failed',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
