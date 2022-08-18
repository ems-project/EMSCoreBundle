<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security\Core;

use EMS\CoreBundle\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;

final class CoreUserAuthenticator
{
    private CoreAuthenticator $coreAuthenticator;
    private GuardAuthenticatorHandler $guardAuthenticatorHandler;
    private RequestStack $requestStack;
    private string $firewallName;

    public function __construct(CoreAuthenticator $coreAuthenticator, GuardAuthenticatorHandler $guardAuthenticatorHandler, RequestStack $requestStack, string $firewallName)
    {
        $this->coreAuthenticator = $coreAuthenticator;
        $this->guardAuthenticatorHandler = $guardAuthenticatorHandler;
        $this->requestStack = $requestStack;
        $this->firewallName = $firewallName;
    }

    public function authenticate(User $user): ?Response
    {
        if (null === $request = $this->requestStack->getCurrentRequest()) {
            throw new AuthenticationException('Missing request');
        }

        return $this->guardAuthenticatorHandler->authenticateUserAndHandleSuccess(
            $user,
            $request,
            $this->coreAuthenticator,
            $this->firewallName
        );
    }
}
