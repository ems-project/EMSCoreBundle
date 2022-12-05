<?php

declare(strict_types=1);

namespace EMS\CoreBundle\EventListener;

use EMS\CoreBundle\Routes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AccessDeniedListener implements EventSubscriberInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 2],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof AccessDeniedException && !$exception instanceof AccessDeniedHttpException) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if (\preg_match('/^\/api\/.*/', $path)) {
            $event->setResponse(
                new JsonResponse([
                    'success' => false,
                    'acknowledged' => true,
                    'error' => 'Access Denied',
                ], Response::HTTP_FORBIDDEN)
            );
            $event->stopPropagation();
        } elseif (null === $event->getRequest()->getUser()) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate(Routes::USER_LOGIN, [
                '_target_path' => $event->getRequest()->getRequestUri(),
            ])));
            $event->stopPropagation();
        }
    }
}
