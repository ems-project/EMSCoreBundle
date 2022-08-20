<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\User;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Environment;

class LoginController
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return new Response($this->twig->render('@EMSCore/user/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]));
    }

    public function logout(): void
    {
        throw new \RuntimeException('You must activate the logout in your security firewall configuration.');
    }
}
