<?php

namespace EMS\CoreBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\ClientHelperBundle\Helper\Environment\Environment;
use EMS\ClientHelperBundle\Helper\Environment\EnvironmentHelper;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Entity\Channel;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Exception\LockedException;
use EMS\CoreBundle\Exception\PrivilegeException;
use EMS\CoreBundle\Repository\ChannelRepository;
use EMS\CoreBundle\Service\IndexService;
use Exception;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment as TwigEnvironment;

class RequestListener
{
    /** @var string */
    public const EMSCO_CHANNEL_ROUTE_REGEX = '/^emsco\\.channel\\.(?P<environment>([a-z\\-0-9_]+))\\..*/';
    public const EMSCO_CHANNEL_PATH_REGEX = '/^\\/channel\\/(?P<channel>([a-z\\-0-9_]+))(\\/)?/';
    private TwigEnvironment $twig;
    private Registry $doctrine;
    private Logger $logger;
    private RouterInterface $router;
    private ChannelRepository $channelRepository;
    private EnvironmentHelper $environmentHelper;
    private IndexService $aliasService;

    public function __construct(TwigEnvironment $twig, Registry $doctrine, Logger $logger, RouterInterface $router, ChannelRepository $channelRepository, EnvironmentHelper $environmentHelper, IndexService $aliasService)
    {
        $this->twig = $twig;
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->router = $router;
        $this->channelRepository = $channelRepository;
        $this->environmentHelper = $environmentHelper;
        $this->aliasService = $aliasService;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if ($event->isMasterRequest()) {
            $matches = [];
            \preg_match(self::EMSCO_CHANNEL_PATH_REGEX, $request->getPathInfo(), $matches);
            foreach ($this->channelRepository->getAll() as $channel) {
                $channelName = $channel->getName();
                if (null === $channelName) {
                    continue;
                }
                $channelAlias = $channel->getAlias();
                if (null === $channelAlias) {
                    continue;
                }

                if (
                    $channelName === ($matches['channel'] ?? null)
                    && !$channel->isPublic()
                    && $this->isAnonymousUser($request)) {
                    throw new AccessDeniedHttpException('Access restricted to authenticated user');
                }

                $baseUrl = \vsprintf('%s://%s%s/channel/%s', [$request->getScheme(), $request->getHttpHost(), $request->getBasePath(), $channelName]);
                $searchConfig = \json_decode($channel->getOptions()['searchConfig'] ?? '{}', true);
                $attributes = \json_decode($channel->getOptions()['attributes'] ?? null, true);

                if (!$this->aliasService->hasIndex($channelAlias)) {
                    $this->logger->warning('log.channel.alias_not_found', [
                        'alias' => $channelAlias,
                        'channel' => $channelName,
                    ]);
                    continue;
                }
                $options = [
                    Environment::BASE_URL_CONFIG => \sprintf('channel/%s', $channelName),
                    Environment::ALIAS_CONFIG => $channelAlias,
                    Environment::ROUTE_PREFIX_CONFIG => Channel::generateChannelRoute($channelName, ''),
                    Environment::REGEX_CONFIG => \sprintf('/^%s/', \preg_quote($baseUrl, '/')),
                    'search_config' => $searchConfig,
                ];

                if (\is_array($attributes)) {
                    $options[Environment::REQUEST_CONFIG] = $attributes;
                }

                $this->environmentHelper->addEnvironment($channelName, $options);
            }
        }

        // TODO: move the next block to the FOS controller:
//        if ($request->get('_route') === $this->userRegistrationRoute && !$this->allowUserRegistration) {
//            $response = new RedirectResponse($this->router->generate($this->userLoginRoute, [], UrlGeneratorInterface::RELATIVE_PATH));
//            $event->setResponse($response);
//        }
//
    }

    public function onKernelException(ExceptionEvent $event)
    {
        //hide all errors to unauthenticated users
        $exception = $event->getThrowable();

        try {
            if ($exception instanceof LockedException || $exception instanceof PrivilegeException) {
                $this->logger->error('log.revision_error', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $exception->getRevision()->getContentType(),
                    EmsFields::LOG_OUUID_FIELD => $exception->getRevision()->getOuuid(),
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $exception->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $exception,
                ]);
                /** @var LockedException $exception */
                if (null == $exception->getRevision()->getOuuid()) {
                    $response = new RedirectResponse($this->router->generate('data.draft_in_progress', [
                            'contentTypeId' => $exception->getRevision()->getContentType()->getId(),
                    ], UrlGeneratorInterface::RELATIVE_PATH));
                } else {
                    $response = new RedirectResponse($this->router->generate('data.revisions', [
                            'type' => $exception->getRevision()->getContentType()->getName(),
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
        } catch (Exception $e) {
            $this->logger->error('log.error', [
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
            ]);
        }
    }

    public function provideTemplateTwigObjects(ControllerEvent $event)
    {
        //TODO: move to twig appextension?
        $repository = $this->doctrine->getRepository('EMSCoreBundle:ContentType');
        $contentTypes = $repository->findBy([
                'deleted' => false,
//                 'rootContentType' => true,
        ], [
                'orderKey' => 'ASC',
        ]);

        $this->twig->addGlobal('contentTypes', $contentTypes);

        $envRepository = $this->doctrine->getRepository('EMSCoreBundle:Environment');
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

    private function isAnonymousUser(Request $request): bool
    {
        return null === $request->getSession()->get('_security_main');
    }
}
