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
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final readonly class AccessDeniedListener implements EventSubscriberInterface
{
    use TargetPathTrait;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private string $coreFirewallName,
    ) {
    }

    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 2],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof AccessDeniedException
            && !$exception instanceof AccessDeniedHttpException) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if (\preg_match('/^\/api\/.*/', $path)) {
            $this->apiAccessDenied($event);
        } elseif (\preg_match('/^\/channel\/.*/', $path)) {
            $this->apiChannelAccessDenied($event);
        }
    }

    private function apiAccessDenied(ExceptionEvent $event): void
    {
        $event->setResponse(new JsonResponse([
            'success' => false,
            'acknowledged' => true,
            'error' => 'Access Denied',
        ], Response::HTTP_FORBIDDEN));
        $event->stopPropagation();
    }

    private function apiChannelAccessDenied(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (null !== $request->getUser()) {
            return;
        }

        if ($request->hasSession() && $request->isMethodSafe() && !$request->isXmlHttpRequest()) {
            $this->saveTargetPath($request->getSession(), $this->coreFirewallName, $request->getUri());
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate(Routes::USER_LOGIN)));
        $event->stopPropagation();
    }
}
