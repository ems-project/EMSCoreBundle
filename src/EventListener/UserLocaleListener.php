<?php

declare(strict_types=1);

namespace EMS\CoreBundle\EventListener;

use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Service\Channel\ChannelRegistrar;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Translation\LocaleSwitcher;

final readonly class UserLocaleListener implements EventSubscriberInterface
{
    public function __construct(private TokenStorageInterface $tokenStorage, private LocaleSwitcher $localeSwitcher)
    {
    }

    /**
     * @return array<string, array{string, int}>
     */
    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 7],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (\preg_match(ChannelRegistrar::EMSCO_CHANNEL_PATH_REGEX, $event->getRequest()->getPathInfo())) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();

        if (!$user instanceof User) {
            return;
        }

        $locale = $user->getLocale();

        $event->getRequest()->setLocale($locale);
        $this->localeSwitcher->setLocale($locale);
    }
}
