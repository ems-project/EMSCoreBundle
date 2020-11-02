<?php

namespace EMS\CoreBundle\EventSubscriber;

use EMS\CoreBundle\Service\UserService;
use FOS\UserBundle\Event\FilterUserResponseEvent;
use FOS\UserBundle\Event\GetResponseUserEvent;
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
            FOSUserEvents::CHANGE_PASSWORD_COMPLETED => 'updateForcePasswordChange',
            FOSUserEvents::CHANGE_PASSWORD_INITIALIZE => 'showMustChangePasswordMessage'
        );
    }

    public function updateForcePasswordChange(FilterUserResponseEvent $event)
    {
        $user = $event->getUser()->setForcePasswordChange(false);
        $event->getRequest()->getSession()->getFlashBag()->clear();
        $this->userService->updateUser($user);
    }

    public function showMustChangePasswordMessage(GetResponseUserEvent $event)
    {
        if ($event->getUser()->getForcePasswordChange()) {
            $event->getRequest()->getSession()->getFlashBag()->add('notice', 'You are required to change your password.');
        }
    }
}