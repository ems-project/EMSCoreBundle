<?php

declare(strict_types=1);

namespace EMS\CoreBundle\EventListener;

use EMS\ClientHelperBundle\Twig\InlineEditExtension;
use EMS\CoreBundle\Core\InlineEditor\InlineEditor;
use EMS\CoreBundle\Routes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class InlineEditListener implements EventSubscriberInterface
{
    public function __construct(private readonly InlineEditor $inlineEditor)
    {
    }

    /**
     * @return array<mixed>
     */
    #[\Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -128],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $route = $request->attributes->get('_route');

        if (!$this->isHtmlRequestResponse($request, $response) || Routes::INLINE_EDIT_EDITOR === $route) {
            return;
        }

        $inlineEditor = $request->attributes->getBoolean(InlineEditExtension::REQUEST_INLINE_EDIT);
        if ($inlineEditor) {
            $this->injectIframe($request, $response);
        }
    }

    private function injectIframe(Request $request, Response $response): void
    {
        if (false === $content = $response->getContent()) {
            return;
        }

        $headPos = \stripos($content, '</head>');
        if (false !== $headPos) {
            $content =
                \substr($content, 0, $headPos)
                ."\n".$this->inlineEditor->renderInjectHead()."\n"
                .\substr($content, $headPos);
        }

        $bodyPos = \strripos($content, '</body>');
        if (false !== $bodyPos) {
            $content =
                \substr($content, 0, $bodyPos)
                ."\n".$this->inlineEditor->renderInjectBody($request)."\n"
                .\substr($content, $bodyPos);
        }

        $response->setContent($content);
    }

    private function isHtmlRequestResponse(Request $request, Response $response): bool
    {
        if ('html' !== $request->getRequestFormat()) {
            return false;
        }

        $contentType = $response->headers->get('Content-Type');

        return $contentType && \str_starts_with(\strtolower($contentType), 'text/html');
    }
}
