<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Security;

use EMS\CoreBundle\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesInterface;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

final class LoginManager
{
    private string $firewallName;
    private TokenStorageInterface $tokenStorage;
    private UserCheckerInterface $userChecker;
    private SessionAuthenticationStrategyInterface $sessionStrategy;
    private RequestStack $requestStack;
    private ?RememberMeServicesInterface $rememberMeService;

    public function __construct(
        string $firewallName,
        TokenStorageInterface $tokenStorage,
        UserCheckerInterface $userChecker,
        SessionAuthenticationStrategyInterface $sessionStrategy,
        RequestStack $requestStack,
        RememberMeServicesInterface $rememberMeService = null
    ) {
        $this->firewallName = $firewallName;
        $this->tokenStorage = $tokenStorage;
        $this->userChecker = $userChecker;
        $this->sessionStrategy = $sessionStrategy;
        $this->requestStack = $requestStack;
        $this->rememberMeService = $rememberMeService;
    }

    public function logInUser(User $user, Response $response = null): void
    {
        $this->userChecker->checkPreAuth($user);

        $token = new UsernamePasswordToken($user, null, $this->firewallName, $user->getRoles());

        $request = $this->requestStack->getCurrentRequest();

        if (null !== $request) {
            $this->sessionStrategy->onAuthentication($request, $token);

            if (null !== $response && null !== $this->rememberMeService) {
                $this->rememberMeService->loginSuccess($request, $response, $token);
            }
        }

        $this->tokenStorage->setToken($token);
    }
}
