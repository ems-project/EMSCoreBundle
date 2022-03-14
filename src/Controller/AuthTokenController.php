<?php

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Security\Authenticator;
use EMS\CoreBundle\Security\Credentials;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class AuthTokenController
{
    private Authenticator $authenticator;
    private Environment $twig;

    public function __construct(Authenticator $authenticator, Environment $twig)
    {
        $this->authenticator = $authenticator;
        $this->twig = $twig;
    }

    public function postAuthTokensAction(Request $request): Response
    {
        try {
            $token = Credentials::usernamePasswordToken($request, Credentials::DEFAULT_PROVIDER_KEY);
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
