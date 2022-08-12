<?php

namespace EMS\CoreBundle\Security;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Entity\AuthToken;
use EMS\CoreBundle\Entity\UserInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class Authenticator
{
    /** @var AuthenticationManagerInterface */
    private $authenticationManager;
    /** @var Registry */
    private $doctrine;

    public function __construct(AuthenticationManagerInterface $authenticationManager, Registry $doctrine)
    {
        $this->authenticationManager = $authenticationManager;
        $this->doctrine = $doctrine;
    }

    public function authenticate(TokenInterface $token): TokenInterface
    {
        return $this->authenticationManager->authenticate($token);
    }

    public function generateAuthToken(TokenInterface $token): AuthToken
    {
        $authToken = new AuthToken($this->getUser($token));

        $em = $this->doctrine->getManager();
        $em->persist($authToken);
        $em->flush();

        return $authToken;
    }

    public function failedResponse(): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(\json_encode([
            'success' => false,
            'acknowledged' => true,
            'error' => ['Unauthorized Error'],
        ]))->setStatusCode(401);

        return $response;
    }

    public function successResponse(string $content): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent($content)->setStatusCode(200);

        return $response;
    }

    private function getUser(TokenInterface $token): UserInterface
    {
        if (!$token->getUser() instanceof UserInterface) {
            $token = $this->authenticate($token);
        }

        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            throw new \RuntimeException(\sprintf('User should be of type %s', UserInterface::class));
        }

        return $user;
    }
}
