<?php
namespace EMS\CoreBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Command\AbstractEmsCommand;
use EMS\CoreBundle\Entity\User;
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
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class RequestListener
{
    protected $twig;
    protected $doctrine;
    protected $logger;
    /**@var \Symfony\Bundle\FrameworkBundle\Routing\Router*/
    protected $router;
    protected $container;
    protected $authorizationChecker;
    protected $session;
    protected $allowUserRegistration;
    protected $userLoginRoute;
    protected $userRegistrationRoute;
    /** @var TokenStorageInterface */
    protected $tokenStorage;

    public function __construct(
        \Twig_Environment $twig,
        Registry $doctrine,
        Logger $logger,
        Router $router,
        Container $container,
        AuthorizationCheckerInterface $authorizationChecker,
        Session $session,
        $allowUserRegistration,
        $userLoginRoute,
        $userRegistrationRoute,
        TokenStorageInterface $tokenStorage
    ) {
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
        $this->tokenStorage = $tokenStorage;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $token = $this->tokenStorage->getToken();
        $route = $event->getRequest()->get('_route');

        if ($token === null || !$route) {
            return;
        }

        $user = $token->getUser();

        if (!$user instanceof User) {
            return;
        }

        $forceChangePassword = $user->getForcePasswordChange();

        if ($forceChangePassword && $route !==  'fos_user_change_password') {
            $response = new RedirectResponse($this->router->generate('fos_user_change_password'));
            $event->setResponse($response);
        }
    }
    
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        //hide all errors to unauthenticated users
        $exception = $event->getException();
        
        try {
            if ($exception instanceof LockedException || $exception instanceof PrivilegeException) {
                $this->logger->error('log.revision_error', [
                    EmsFields::LOG_CONTENTTYPE_FIELD =>  $exception->getRevision()->getContentType(),
                    EmsFields::LOG_OUUID_FIELD =>  $exception->getRevision()->getOuuid(),
                    EmsFields::LOG_ERROR_MESSAGE_FIELD =>  $exception->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD =>  $exception,
                ]);
                /** @var LockedException $exception */
                if (null == $exception->getRevision()->getOuuid()) {
                    $response = new RedirectResponse($this->router->generate('data.draft_in_progress', [
                            'contentTypeId' => $exception->getRevision()->getContentType()->getId(),
                    ], UrlGeneratorInterface::RELATIVE_PATH));
                } else {
                    $response = new RedirectResponse($this->router->generate('data.revisions', [
                            'type' => $exception->getRevision()->getContentType()->getName(),
                            'ouuid' => $exception->getRevision()->getOuuid()
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
                'orderKey' => 'ASC'
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
