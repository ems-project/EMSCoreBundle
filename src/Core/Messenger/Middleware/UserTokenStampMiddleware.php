<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Messenger\Middleware;

use EMS\CoreBundle\Core\Messenger\Stamp\UserTokenStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class UserTokenStampMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $token = $this->tokenStorage->getToken();

        if ($token instanceof TokenInterface) {
            $envelope = $envelope->with(new UserTokenStamp($token));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
