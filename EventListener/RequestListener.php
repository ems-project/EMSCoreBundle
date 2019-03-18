<?php
namespace EMS\CoreBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Command\AbstractEmsCommand;
use EMS\CoreBundle\Command\JobOutput;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Exception\LockedException;
use EMS\CoreBundle\Exception\PrivilegeException;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
    
    public function __construct(\Twig_Environment $twig, Registry $doctrine, Logger $logger, Router $router, Container $container, AuthorizationCheckerInterface $authorizationChecker, Session $session, $allowUserRegistration, $userLoginRoute, $userRegistrationRoute)
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
    }
    
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        //hide all errors to unauthenticated users
        $exception = $event->getException();
        
        try {
            if ($exception instanceof LockedException || $exception instanceof PrivilegeException) {
                $this->session->getFlashBag()->add('error', $exception->getMessage());
                /** @var LockedException $exception */
                if (null == $exception->getRevision()->getOuuid()) {
                    $response = new RedirectResponse($this->router->generate('data.draft_in_progress', [
                            'contentTypeId' => $exception->getRevision()->getContentType()->getId(),
                    ], UrlGeneratorInterface::RELATIVE_PATH));
                } else {
                    $response = new RedirectResponse($this->router->generate('data.revisions', [
                            'type' => $exception->getRevision()->getContentType()->getName(),
                            'ouuid'=> $exception->getRevision()->getOuuid()
                    ], UrlGeneratorInterface::RELATIVE_PATH));
                }
                $event->setResponse($response);
            }
            if ($exception instanceof ElasticmsException) {
                $this->session->getFlashBag()->add('error', $exception->getMessage());
                $response = new RedirectResponse($this->router->generate('notifications.list', [
                    ]));
                $event->setResponse($response);
            }
        } catch (\Exception $e) {
            /**
             * Todo: add logger instead of dumping on screen, this throws the following error on PHPStan level 0:
             *
             * Function dump not found. Because it is not run in a dev environment. We could add dump to the scope of PHPStan,
             * but I think it is a good idea to throw errors on dump statements.
             *
             * "method_exists" is supported by PHPStan, but is a bad design choice,
             * However, "function_exists" and "class_exists" are not supported:
             *
             * https://github.com/phpstan/phpstan/issues/246
             **/
            if (function_exists('dump')) {
                dump($e);
            }
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
