<?php

namespace EMS\CoreBundle\Security\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\PreAuthenticatedToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\SimplePreAuthenticatorInterface;

class ApiAuthenticator implements SimplePreAuthenticatorInterface
{
    /**
     * @param string $providerKey
     */
    public function createToken(Request $request, $providerKey): PreAuthenticatedToken
    {
        // look for an apikey header value
        $apiKey = $request->headers->get('X-Auth-Token');
//         $apiKey = $request->query->get('apikey');

        // or if you want to use an "apikey" header, then do something like this:
        // $apiKey = $request->headers->get('apikey');
        if (!$apiKey) {
            throw new BadCredentialsException();
            // or to just skip api key authentication
            // return null;
        }

        return new PreAuthenticatedToken(
            'anon.',
            $apiKey,
            $providerKey
        );
    }

    /**
     * @param string $providerKey
     */
    public function supportsToken(TokenInterface $token, $providerKey): bool
    {
        return $token instanceof PreAuthenticatedToken && $token->getProviderKey() === $providerKey;
    }

    /**
     * @param string $providerKey
     */
    public function authenticateToken(TokenInterface $token, UserProviderInterface $userProvider, $providerKey): PreAuthenticatedToken
    {
        if (!$userProvider instanceof ApiUserProvider) {
            throw new \InvalidArgumentException(\sprintf('The user provider must be an instance of ApiKeyUserProvider (%s was given).', \get_class($userProvider)));
        }

        $apiKey = $token->getCredentials();
        $username = $userProvider->getUsernameForApiKey($apiKey);

        if (!$username) {
            // CAUTION: this message will be returned to the client
            // (so don't put any un-trusted messages / error strings here)
            throw new CustomUserMessageAuthenticationException(\sprintf('API Key "%s" does not exist.', $apiKey));
        }

        $user = $userProvider->loadUserByUsername($username);

        return new PreAuthenticatedToken(
            $user,
            $apiKey,
            $providerKey,
            $user->getRoles()
        );
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new Response(
            // this contains information about *why* authentication failed
                // use it, or return your own message
            \strtr($exception->getMessageKey(), $exception->getMessageData()),
            401
        );
    }
}
