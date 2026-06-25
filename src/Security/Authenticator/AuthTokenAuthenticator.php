<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security\Authenticator;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class AuthTokenAuthenticator extends AbstractAuthenticator
{
    #[\Override]
    public function supports(Request $request): bool
    {
        return null !== $this->getApiToken($request);
    }

    #[\Override]
    public function authenticate(Request $request): Passport
    {
        $apiToken = $this->getApiToken($request);
        if (null === $apiToken) {
            throw new CustomUserMessageAuthenticationException('No API token provided');
        }

        return new SelfValidatingPassport(new UserBadge($apiToken));
    }

    #[\Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    #[\Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse([
            'success' => false,
            'acknowledged' => true,
            'error' => 'Authentication failed',
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function getApiToken(Request $request): ?string
    {
        $legacyApiToken = $request->headers->get('X-Auth-Token');
        if (\is_string($legacyApiToken) && '' !== $legacyApiToken) {
            return $legacyApiToken;
        }

        $authorization = $request->headers->get('Authorization');
        if (!\is_string($authorization)) {
            return null;
        }

        if (1 !== \preg_match('/^\s*Bearer\s+(.+)\s*$/i', $authorization, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
