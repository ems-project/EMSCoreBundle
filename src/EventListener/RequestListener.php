<?php

declare(strict_types=1);

namespace EMS\CoreBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Core\Log\LogRevisionContext;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Exception\LockedException;
use EMS\CoreBundle\Exception\PrivilegeException;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\Channel\ChannelRegistrar;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as TwigEnvironment;

class RequestListener
{
    public function __construct(private readonly ChannelRegistrar $channelRegistrar, private readonly TwigEnvironment $twig, private readonly Registry $doctrine, private readonly Logger $logger, private readonly RouterInterface $router)
    {
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
        $redirectUrl = $event->getRequest()->get('redirectToUrl');
        if ($response instanceof RedirectResponse && \is_string($redirectUrl)) {
            $response->setTargetUrl($redirectUrl);
        }
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        // hide all errors to unauthenticated users
        $exception = $event->getThrowable();

        try {
            if ($exception instanceof LockedException || $exception instanceof PrivilegeException) {
                $this->logger->error($exception instanceof LockedException ? 'log.locked_exception_error' : 'log.privilege_exception_error', [...['username' => $exception->getRevision()->getLockBy()], ...LogRevisionContext::read($exception->getRevision())]);
                if (null == $exception->getRevision()->getOuuid()) {
                    $response = new RedirectResponse($this->router->generate('data.draft_in_progress', [
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
                $this->logger->error('log.error', [
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $exception->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $exception,
                ]);
                $response = new RedirectResponse($this->router->generate('notifications.list', [
                ]));
                $event->setResponse($response);
            }
        } catch (\Exception $e) {
            $this->logger->error('log.error', [
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
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
