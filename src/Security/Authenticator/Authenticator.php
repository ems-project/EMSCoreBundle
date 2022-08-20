<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security\Authenticator;

use EMS\CoreBundle\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;

final class Authenticator
{
    private FormLoginAuthenticator $formLoginAuthenticator;
    private GuardAuthenticatorHandler $guardAuthenticatorHandler;
    private RequestStack $requestStack;
    private string $firewallName;

    public function __construct(
        FormLoginAuthenticator $formLoginAuthenticator,
        GuardAuthenticatorHandler $guardAuthenticatorHandler,
        RequestStack $requestStack,
        string $firewallName)
    {
        $this->formLoginAuthenticator = $formLoginAuthenticator;
        $this->guardAuthenticatorHandler = $guardAuthenticatorHandler;
        $this->requestStack = $requestStack;
        $this->firewallName = $firewallName;
    }

    public function authenticate(User $user): void
    {
        if (null === $request = $this->requestStack->getCurrentRequest()) {
            throw new AuthenticationException('Missing request');
        }

        $token = $this->formLoginAuthenticator->createAuthenticatedToken($user, $this->firewallName);
        $this->guardAuthenticatorHandler->authenticateWithToken($token, $request, $this->firewallName);
    }
}
