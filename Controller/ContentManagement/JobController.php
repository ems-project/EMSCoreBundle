<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use EMS\CoreBundle;
use EMS\CoreBundle\Command\AbstractEmsCommand;
use EMS\CoreBundle\Command\JobOutput;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\Job;
use EMS\CoreBundle\Form\Form\JobType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;
use SensioLabs\AnsiConverter\Theme\Theme;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Request;

class JobController extends AppController
{

	/**
	 * @Route("/job/status/{job}", name="job.status"))
	 */
	public function jobStatusAction(Job $job, Request $request)
	{
	
		$theme = new Theme();
		$converter = new AnsiToHtmlConverter ( $theme );
		
		return $this->render ( 'EMSCoreBundle:job:status.html.twig', [ 
				'job' => $job,
				'output' => $converter->convert ( $job->getOutput () ) 
		] );
	}
	
	
	
    public static function getArgv ($string) {
    	preg_match_all ('/(?<=^|\s)([\'"]?)(.+?)(?<!\\\\)\1(?=$|\s)/', $string, $ms);
    	return $ms[2];
    }
	
	
	/**
	 * @Route("/admin/job/start/{job}", name="job.start"))
	 * 
	 * @method ({"POST"})
	 */
	public function startJobAction(Job $job, Request $request) {
		if (! $job->getStarted () && ! $job->getDone ()) {
			/**@var EntityManager $manager */
			$manager = $this->getDoctrine()->getManager ();
			/** @var \EMS\CoreBundle\Repository\JobRepository $jobRepository */
			$jobRepository = $this->getDoctrine()->getRepository ( 'EMSCoreBundle:Job' );
			$output = new JobOutput( $this->getDoctrine(), $job );
			$output->writeln ( "Job ready to be launch" );
			
			$job->setStarted( true );
			$this->getDoctrine()->getManager ()->persist ( $job );
			$this->getDoctrine()->getManager ()->flush ( $job );
			
			try {
				
				try {
					if (null !== $job->getService ()) {
						try {
							/** @var AbstractEmsCommand $command */
							$command = $this->container->get ( $job->getService () );
							$input = new ArrayInput( $job->getArguments () );
							$command->run ( $input, $output );
							$output->writeln ( "Job done" );
						} catch ( ServiceNotFoundException $e ) {
							$output->writeln ( "<error>Service not found</error>" );
						}
					} else {
						$command = $job->getCommand ();
						if (null === $command) {
							$command = "list";
						}
						
						/** @var \AppKernel $kernel */
						$kernel = $this->container->get ( 'kernel' );
						$application = new Application ( $kernel );
						$application->setAutoExit ( false );
						
						$input = new ArgvInput ( $this->getArgv ( "console " . $command ) );
						$application->run ( $input, $output );
						$output->writeln ( "Job done" );
					}
				} catch ( InvalidArgumentException $e ) {
					$output->writeln ( "<error>" . $e->getMessage () . "</error>" );
				}
				
				$job->setDone ( true );
				$job->setProgress ( 100 );
				
				$this->getDoctrine()->getManager ()->persist ( $job );
				$this->getDoctrine()->getManager ()->flush ( $job );
				$this->getLogger()->info ( 'Job ' . $job->getCommand(). ' completed.' );
			} catch ( \Exception $e ) {
				$output->writeln ( "An exception has been raised!" );
				$output->writeln ( "Exception:".$e->getMessage() );
				$job->setDone(true);
				$this->getDoctrine()->getManager ()->persist ( $job );
				$this->getDoctrine()->getManager ()->flush ( $job );
				// not an internal redirect
			}
			
		}
		return $this->redirectToRoute('job.status', [
				'job' => $job->getId(),
		]);
	}
	
	/**
	 * @Route("/admin/job/delete/{job}", name="job.delete"))
	 * @Method({"POST"})
	 */
	public function removeAction(Job $job, Request $request)
	{
		$manager = $this->getDoctrine()->getManager();
		$manager->remove($job);
		$manager->flush();
	
	
		return $this->redirectToRoute('job.index');
	
	}	
	
	/**
	 * @Route("/admin/job/add", name="job.add"))
	 */
	public function createAction(Request $request)
	{
		$job = new Job();
		$form = $this->createForm ( JobType::class, $job );
		
		$form->handleRequest ( $request );
		
		if ($form->isSubmitted () && $form->isValid ()) {
			return $this->startConsole($job);
		}
		
		return $this->render( 'EMSCoreBundle:job:add.html.twig', [
				'form' => $form->createView()
		]);
	}
	
	/**
	 * @Route("/admin/job/clean", name="job.clean"))
     * @Method({"POST"})
	 */
	public function cleanDoneAction(Request $request)
	{	
		/**@var EntityManager $manager */
		$manager = $this->getDoctrine()->getManager();
		/** @var \EMS\CoreBundle\Repository\JobRepository $jobRepository */
		$jobRepository = $manager->getRepository("EMSCoreBundle:Job");
		$result = $jobRepository->findBy(['done' => true]);
		foreach($result as $job){
			$manager->remove($job);			
		}
		$manager->flush();
	
		return $this->redirectToRoute('job.index');
		
	}
	
	/**
	 * @Route("/admin/job", name="job.index"))
	 */
	public function indexAction(Request $request)
	{			
		if(null != $request->query->get('page')){
			$page = $request->query->get('page');
		}
		else{
			$page = 1;
		}
		
		/** @var EntityManagerInterface $em */
		$em = $this->getDoctrine()->getManager();
		/** @var \EMS\CoreBundle\Repository\JobRepository $jobRepository */
		$jobRepository = $em->getRepository("EMSCoreBundle:Job");
		
		$size = $this->container->getParameter('ems_core.paging_size');
		$from  = ($page-1)*$size;
		$total = $jobRepository->countJobs();
		$lastPage = ceil($total/$size);
		
		
		$jobs = $jobRepository->findBy([], ['created' => 'DESC'], $size, $from);
		
		return $this->render( 'EMSCoreBundle:job:index.html.twig', [
				'jobs' =>  $jobs,
				'page' => $page,
				'size' => $size,
				'from' => $from,
				'lastPage' => $lastPage,
				'paginationPath' => 'job.index',
		] );
	}
}