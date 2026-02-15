<?php

declare(strict_types=1);

namespace EMS\CoreBundle\EventListener;

use EMS\CoreBundle\Core\UI\Page\Page;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class PageListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $templateNamespace,
    ) {
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => 'onView',
        ];
    }

    public function onView(ViewEvent $event): void
    {
        $page = $event->getControllerResult();
        if (!$page instanceof Page) {
            return;
        }

        $content = $this->twig->render(\sprintf('@%s/', $this->templateNamespace).$page->template, $page->context);
        $event->setResponse(new Response($content, Response::HTTP_OK));
    }
}
