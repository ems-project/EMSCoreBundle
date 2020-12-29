<?php

namespace EMS\CoreBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Exception\LockedException;
use EMS\CoreBundle\Exception\PrivilegeException;
use Exception;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class RequestListener
{
    /** @var string */
    public const EMSCO_CHANNEL_ROUTE_REGEX = '/^emsco\\.channel\\.(?P<environment>([a-z\\-0-1_]+))\\..*/';
    protected $twig;
    protected $doctrine;
    protected $logger;
    /** @var RouterInterface */
    protected $router;
    protected $container;
    protected $authorizationChecker;
    protected $session;
    protected $allowUserRegistration;
    protected $userLoginRoute;
    protected $userRegistrationRoute;

    public function __construct(\Twig_Environment $twig, Registry $doctrine, Logger $logger, RouterInterface $router, Container $container, AuthorizationCheckerInterface $authorizationChecker, Session $session, $allowUserRegistration, $userLoginRoute, $userRegistrationRoute)
    {
        $this->twig = $twig;
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->router = $router;
        $this->container = $container;
        $this->authorizationChecker = $authorizationChecker;
        $this->session = $session;
        $this->allowUserRegistration = $allowUserRegistration;
        $this->userLoginRoute = $userLoginRoute;
        $this->userRegistrationRoute = $userRegistrationRoute;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if ($event->getRequest()->get('_route') === $this->userRegistrationRoute && !$this->allowUserRegistration) {
            $response = new RedirectResponse($this->router->generate($this->userLoginRoute, [], UrlGeneratorInterface::RELATIVE_PATH));
            $event->setResponse($response);
        }

        $route = $event->getRequest()->get('_route');
        $matches = [];
        if (\is_string($route) && 1 === \preg_match(self::EMSCO_CHANNEL_ROUTE_REGEX, $route, $matches)) {
            $request = $event->getRequest();
            $environment = $matches['environment'] ?? null;
            if (!\is_string($environment)) {
                throw new \RuntimeException('Unexpected not found environment in matching route');
            }

            $request->attributes->set('_environment', $environment);
        }
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        //hide all errors to unauthenticated users
        $exception = $event->getException();

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

    public function provideTemplateTwigObjects(FilterControllerEvent $event)
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
        foreach ($contentTypes as $contentType) {
            $defaultEnvironments[] = $contentType->getName();
        }

        $this->twig->addGlobal('defaultEnvironments', $defaultEnvironments);
    }
}
