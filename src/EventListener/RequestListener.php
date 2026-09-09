<?php

declare(strict_types=1);

namespace EMS\CoreBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Core\Log\LogRevisionContext;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Exception\LockedException;
use EMS\CoreBundle\Exception\PrivilegeException;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\Channel\ChannelRegistrar;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as TwigEnvironment;

use function Symfony\Component\Translation\t;

class RequestListener
{
    public function __construct(
        private readonly ChannelRegistrar $channelRegistrar,
        private readonly TwigEnvironment $twig,
        private readonly Registry $doctrine,
        private readonly LocalizedLoggerInterface $logger,
        private readonly RouterInterface $router
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->channelRegistrar->register($event->getRequest());
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $redirectUrl = $this->getSafeRedirectTarget($event->getRequest()->query->get('redirectToUrl'));

        if ($response instanceof RedirectResponse && null !== $redirectUrl) {
            $response->setTargetUrl($redirectUrl);
        }
    }

    private function getSafeRedirectTarget(mixed $redirectUrl): ?string
    {
        if (!\is_string($redirectUrl) || '' === $redirectUrl) {
            return null;
        }

        if (1 !== \preg_match('/^\/(?!\/)/', $redirectUrl) || \str_contains($redirectUrl, '\\') || 1 === \preg_match('/[\x00-\x1F\x7F]/', $redirectUrl)) {
            return null;
        }

        $parts = \parse_url($redirectUrl);
        if (false === $parts) {
            return null;
        }

        foreach (['scheme', 'host', 'port', 'user', 'pass'] as $component) {
            if (isset($parts[$component])) {
                return null;
            }
        }

        return $redirectUrl;
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        // hide all errors to unauthenticated users
        $exception = $event->getThrowable();

        try {
            if ($exception instanceof LockedException || $exception instanceof PrivilegeException) {
                if ($exception instanceof LockedException) {
                    $this->logger->messageError(t('message.revision_locked', [
                        'label' => $exception->getRevision()->getLabel(),
                        'username' => $exception->getRevision()->getLockBy(),
                    ], 'emsco-core'), LogRevisionContext::update($exception->getRevision()));
                }
                if ($exception instanceof PrivilegeException) {
                    $this->logger->messageError(
                        t('message.privilege_exception', [], 'emsco-core'),
                        LogRevisionContext::read($exception->getRevision())
                    );
                }

                if (null == $exception->getRevision()->getOuuid()) {
                    $response = new RedirectResponse($this->router->generate('emsco_draft_in_progress', [
                        'contentTypeId' => $exception->getRevision()->giveContentType()->getId(),
                    ], UrlGeneratorInterface::RELATIVE_PATH));
                } else {
                    $response = new RedirectResponse($this->router->generate(Routes::VIEW_REVISIONS, [
                        'type' => $exception->getRevision()->giveContentType()->getName(),
                        'ouuid' => $exception->getRevision()->getOuuid(),
                    ], UrlGeneratorInterface::RELATIVE_PATH));
                }
                $event->setResponse($response);
            }
            if ($exception instanceof ElasticmsException) {
                $this->logger->messageError(t('message.action_error', [
                    'error_message' => $exception->getMessage(),
                ], 'emsco-core'), [
                    EmsFields::LOG_EXCEPTION_FIELD => $exception,
                ]);
                $response = new RedirectResponse($this->router->generate('notifications.list', [
                ]));
                $event->setResponse($response);
            }
        } catch (\Exception $exception) {
            $this->logger->messageError(t('message.action_error', [
                'error_message' => $exception->getMessage(),
            ], 'emsco-core'), [
                EmsFields::LOG_EXCEPTION_FIELD => $exception,
            ]);
        }
    }

    public function provideTemplateTwigObjects(ControllerEvent $event): void
    {
        // TODO: move to twig appextension?
        $repository = $this->doctrine->getRepository(ContentType::class);
        $contentTypes = $repository->findBy([
            'deleted' => false,
            //                 'rootContentType' => true,
        ], [
            'orderKey' => 'ASC',
        ]);

        $this->twig->addGlobal('contentTypes', $contentTypes);

        $envRepository = $this->doctrine->getRepository(Environment::class);
        $contentTypes = $envRepository->findBy([
            'inDefaultSearch' => true,
        ]);

        $defaultEnvironments = [];
        /** @var ContentType $contentType */
        foreach ($contentTypes as $contentType) {
            $defaultEnvironments[] = $contentType->getName();
        }

        $this->twig->addGlobal('defaultEnvironments', $defaultEnvironments);
    }
}
