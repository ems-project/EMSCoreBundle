<?php

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Security\Authenticator;
use EMS\CoreBundle\Security\Credentials;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class AuthTokenController
{
    /** @var Authenticator */
    private $authenticator;
    /** @var Environment */
    private $twig;

    public function __construct(Authenticator $authenticator, Environment $twig)
    {
        $this->authenticator = $authenticator;
        $this->twig = $twig;
    }

    /**
     * @Route("/auth-token", name="auth-token", defaults={"_format": "json"}, methods={"POST"})
     */
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
