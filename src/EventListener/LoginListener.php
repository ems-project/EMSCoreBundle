<?php

declare(strict_types=1);

namespace EMS\CoreBundle\EventListener;

use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

final class LoginListener implements EventSubscriberInterface
{
    public function __construct(private readonly UserManager $userManager)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => 'onLogin',
        ];
    }

    public function onLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();

        if (!$user instanceof User) {
            return;
        }

        if ($user->isExpired()) {
            throw new AccessDeniedException('Access Denied: Your account has expired.');
        }

        $user->setLastLogin(new \DateTime());
        $this->userManager->update($user);
    }
}
