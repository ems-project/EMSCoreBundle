<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security\Authenticator;

use EMS\CoreBundle\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

final readonly class Authenticator
{
    public function __construct(private FormLoginAuthenticator $formLoginAuthenticator, private UserAuthenticatorInterface $userAuthenticator, private RequestStack $requestStack)
    {
    }

    public function authenticate(User $user): void
    {
        if (null === $request = $this->requestStack->getCurrentRequest()) {
            throw new AuthenticationException('Missing request');
        }

        $this->userAuthenticator->authenticateUser($user, $this->formLoginAuthenticator, $request);
    }
}
