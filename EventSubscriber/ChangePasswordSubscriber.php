<?php

namespace EMS\CoreBundle\EventSubscriber;

use EMS\CoreBundle\Service\UserService;
use FOS\UserBundle\Event\FilterUserResponseEvent;
use FOS\UserBundle\FOSUserEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ChangePasswordSubscriber implements EventSubscriberInterface
{
    /** @var UserService */
    private $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            FOSUserEvents::CHANGE_PASSWORD_COMPLETED => 'updateForcePasswordChange'
        );
    }

    public function updateForcePasswordChange(FilterUserResponseEvent $event)
    {
        $user = $event->getUser();
        $user->setForcePasswordChange(false);
        $this->userService->updateUser($user);
    }
}