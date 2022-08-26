<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security\Authenticator;

use EMS\CoreBundle\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

final class Authenticator
{
    private FormLoginAuthenticator $formLoginAuthenticator;
    private UserAuthenticatorInterface $userAuthenticator;
    private RequestStack $requestStack;

    public function __construct(
        FormLoginAuthenticator $formLoginAuthenticator,
        UserAuthenticatorInterface $userAuthenticator,
        RequestStack $requestStack
    ) {
        $this->formLoginAuthenticator = $formLoginAuthenticator;
        $this->userAuthenticator = $userAuthenticator;
        $this->requestStack = $requestStack;
    }

    public function authenticate(User $user): void
    {
        if (null === $request = $this->requestStack->getCurrentRequest()) {
            throw new AuthenticationException('Missing request');
        }

        $this->userAuthenticator->authenticateUser($user, $this->formLoginAuthenticator, $request);
    }
}
