<?php

namespace EMS\CoreBundle\EventSubscriber;

use EMS\CoreBundle\Service\UserService;
use FOS\UserBundle\Event\FilterUserResponseEvent;
use FOS\UserBundle\Event\GetResponseUserEvent;
use FOS\UserBundle\FOSUserEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use EMS\CoreBundle\Entity\User;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;

class ChangePasswordSubscriber implements EventSubscriberInterface
{
    /** @var UserService */
    private $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * @return array<string>
     */
    public static function getSubscribedEvents(): array
    {
        return array(
            FOSUserEvents::CHANGE_PASSWORD_COMPLETED => 'updateForcePasswordChange',
            FOSUserEvents::CHANGE_PASSWORD_INITIALIZE => 'showMustChangePasswordMessage'
        );
    }

    public function updateForcePasswordChange(FilterUserResponseEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();
        $user->setForcePasswordChange(false);
        $this->userService->updateUser($user);

        /** @var Session<array> $session */
        $session = $event->getRequest()->getSession();

        /** @var FlashBag $flashBag */
        $flashBag = $session->getFlashBag();
        $flashBag->clear();
        $flashBag->add('notice', 'Your password has been updated.');
    }

    public function showMustChangePasswordMessage(GetResponseUserEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();
        if ($user->getForcePasswordChange()) {

            /** @var Session<array> $session */
            $session = $event->getRequest()->getSession();

            /** @var FlashBag $flashBag */
            $flashBag = $session->getFlashBag();
            $flashBag->add('notice', 'You are required to change your password.');
        }
    }
}