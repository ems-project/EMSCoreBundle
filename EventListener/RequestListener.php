<?php 
namespace EMS\CoreBundle\EventListener;


use EMS\CoreBundle\Command\AbstractEmsCommand;
use EMS\CoreBundle\Command\JobOutput;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use EMS\CoreBundle\Exception\LockedException;
use Symfony\Component\HttpFoundation\Session\Session;
use EMS\CoreBundle\Exception\PrivilegeException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

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
		if($event->getRequest()->get('_route') === $this->userRegistrationRoute && !$this->allowUserRegistration) {
			$response = new RedirectResponse($this->router->generate($this->userLoginRoute, [
			]));
			$event->setResponse($response);
		}
	}
	
	public function onKernelException(GetResponseForExceptionEvent $event)
	{
		//hide all errors to unauthenticated users        
		$exception = $event->getException();
		
		try {
			if (!($exception instanceof NotFoundHttpException) && !$this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY')) {
				$response = new RedirectResponse($this->router->generate($this->userLoginRoute));
				$event->setResponse($response);
			}
			else if($exception instanceof LockedException || $exception instanceof PrivilegeException) {
				$this->session->getFlashBag()->add('error', $exception->getMessage());
				/** @var LockedException $exception */
				if(null == $exception->getRevision()->getOuuid()){
					$response = new RedirectResponse($this->router->generate('data.draft_in_progress', [
							'contentTypeId' => $exception->getRevision()->getContentType()->getId(),
					]));
				}
				else {
					$response = new RedirectResponse($this->router->generate('data.revisions', [
							'type' => $exception->getRevision()->getContentType()->getName(),
							'ouuid'=> $exception->getRevision()->getOuuid()
					]));				
				}
				$event->setResponse($response);
			}
		}
		catch(\Exception $e){
			if(function_exists('dump')){
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
//     			'rootContentType' => true,
    	],[
    			'orderKey' => 'ASC'
    	]);

    	$this->twig->addGlobal('contentTypes', $contentTypes);
    	
    	$envRepository = $this->doctrine->getRepository('EMSCoreBundle:Environment');
    	$contentTypes = $envRepository->findBy([
    			'inDefaultSearch' => true,
    	]);
    	
    	$defaultEnvironments = [];
    	foreach ($contentTypes as $contentType){
    		$defaultEnvironments[] = $contentType->getName();
    	}

    	$this->twig->addGlobal('defaultEnvironments', $defaultEnvironments);
    }
    
    public static function getArgv ($string) {
    	preg_match_all ('/(?<=^|\s)([\'"]?)(.+?)(?<!\\\\)\1(?=$|\s)/', $string, $ms);
    	return $ms[2];
    }
    
    public function startJob($event)
    {
    	if( $event->getRequest()->isMethod('POST') && $event->getResponse() instanceof RedirectResponse ){
    		/** @var RedirectResponse $redirect */
    		$redirect = $event->getResponse();
    		try {
	    		$params = $this->router->match($redirect->getTargetUrl());
	    		
	    		if(isset($params['_route']) && $params['_route'] == "job.status" && isset($params['job'])){
	    			$this->logger->info('Job '.$params['job'].' can be started');
	    			
	    			/** @var \EMS\CoreBundle\Repository\JobRepository $jobRepository */
	    			$jobRepository = $this->doctrine->getRepository('EMSCoreBundle:Job');
	    			/** @var \EMS\CoreBundle\Entity\Job $job */
	    			$job = $jobRepository->find($params['job']);
	    			if($job && !$job->getDone()){
	    				
	    				$output = new JobOutput($this->doctrine, $job);
	    				$output->writeln("Job ready to be launch");
	    				
	    				try{
		    				if(null !== $job->getService()){
		    					try{
				    				/** @var AbstractEmsCommand $command */
				    				$command = $this->container->get($job->getService());
		    						$input = new ArrayInput($job->getArguments());
				    				$command->run($input, $output);    					
			    					$output->writeln("Job done");
		    					}
		    					catch (ServiceNotFoundException $e){
		    						$output->writeln("<error>Service not found</error>");
		    					}
		    				}
		    				else {
		    					$command = $job->getCommand();
		    					if(null === $command){
		    						$command = "list";
		    					}
		    					
		    					/** @var \AppKernel $kernel */
		    					$kernel = $this->container->get('kernel');
		    					$application = new Application($kernel);
		    					$application->setAutoExit(false);
		    					
		    					
		    					$input = new ArgvInput($this->getArgv("console ".$command));
		    					$application->run($input, $output);
			    				$output->writeln("Job done");
		    				}
	    				}
	    				catch (InvalidArgumentException $e){
	    					$output->writeln("<error>".$e->getMessage()."</error>");
	    				}
	    		
	    				$job->setDone(true);
	    				$job->setProgress(100);
	    				
	    				$this->doctrine->getManager()->persist($job);
	    				$this->doctrine->getManager()->flush($job);
	    				$this->logger->info('Job '.$params['job'].' completed.');
	    			}
	    		}
    		}
    		catch(ResourceNotFoundException $e) {
    			//not an internal redirect
    		}
    	}
    	
    }
	
}


