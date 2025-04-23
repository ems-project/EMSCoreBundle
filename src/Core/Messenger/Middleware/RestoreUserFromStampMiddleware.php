<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Messenger\Middleware;

use EMS\CoreBundle\Core\Messenger\Stamp\UserTokenStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RestoreUserFromStampMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        /** @var UserTokenStamp|null $stamp */
        $stamp = $envelope->last(UserTokenStamp::class);

        if ($stamp) {
            $this->tokenStorage->setToken($stamp->token);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
