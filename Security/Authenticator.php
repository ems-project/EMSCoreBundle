<?php

namespace EMS\CoreBundle\Security;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Entity\AuthToken;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Service\UserService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

class Authenticator
{
    /** @var Registry */
    private $doctrine;
    /** @var EncoderFactoryInterface */
    private $encoderFactory;
    /** @var UserService */
    private $userService;

    public function __construct(Registry $doctrine, EncoderFactoryInterface $encoderFactory, UserService $userService)
    {
        $this->doctrine = $doctrine;
        $this->encoderFactory = $encoderFactory;
        $this->userService = $userService;
    }

    public function authenticate(UsernamePasswordToken $token): void
    {
        $user = $this->userService->getUser($token->getUsername(), false);
        if (empty($user)) {
            throw new \RuntimeException("User not found");
        }

        $encoder = $this->encoderFactory->getEncoder($user);
        if ($encoder->isPasswordValid($user->getPassword(), $token->getCredentials(), $user->getSalt())) {
            $token->eraseCredentials();
            $token->setUser($user);
        }
    }

    public function generateAuthToken(UsernamePasswordToken $token): AuthToken
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
        $response->setContent(json_encode([
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

    private function getUser(UsernamePasswordToken $token): UserInterface
    {
        if (!$token->getUser() instanceof UserInterface) {
            $this->authenticate($token);
        }

        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            throw new \RuntimeException(\sprintf('User should be of type %s', UserInterface::class));
        }
        return $user;
    }
}
