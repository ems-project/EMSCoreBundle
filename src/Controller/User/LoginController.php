<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\User;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Environment;

class LoginController
{
    public function __construct(private readonly Environment $twig, private readonly string $templateNamespace)
    {
    }

    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return new Response($this->twig->render("@$this->templateNamespace/user/login.html.twig", [
            'last_username' => $lastUsername,
            'error' => $error,
        ]));
    }

    public function logout(): never
    {
        throw new \RuntimeException('You must activate the logout in your security firewall configuration.');
    }
}
