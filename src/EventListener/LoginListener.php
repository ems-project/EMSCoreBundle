<?php

declare(strict_types=1);

namespace EMS\CoreBundle\EventListener;

use EMS\CoreBundle\Core\User\UserManager;
use EMS\CoreBundle\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final readonly class LoginListener implements EventSubscriberInterface
{
    public function __construct(private UserManager $userManager)
    {
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        if ('ems_core' !== $event->getFirewallName()) {
            return;
        }

        $user = $event->getUser();

        if ($user instanceof User) {
            $user->setLastLogin(new \DateTime());
            $this->userManager->update($user);
        }
    }
}
