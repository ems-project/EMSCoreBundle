<?php

namespace EMS\CoreBundle\Controller\Api;

use EMS\CoreBundle\Security\Authenticator;
use EMS\CoreBundle\Security\Credentials;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class AuthTokenController
{
    private Authenticator $authenticator;
    private Environment $twig;
    private string $firewallName;

    public function __construct(Authenticator $authenticator, Environment $twig, string $firewallName)
    {
        $this->authenticator = $authenticator;
        $this->twig = $twig;
        $this->firewallName = $firewallName;
    }

    public function postAuthTokensAction(Request $request): Response
    {
        try {
            $token = Credentials::usernamePasswordToken($request, $this->firewallName);
            $authToken = $this->authenticator->generateAuthToken($token);
        } catch (\Exception $exception) {
            return $this->authenticator->failedResponse();
        }

        return $this->authenticator->successResponse(
            $this->twig->render('@EMSCore/ajax/auth-token.json.twig', [
                'authToken' => $authToken,
                'success' => true,
            ])
        );
    }
}
