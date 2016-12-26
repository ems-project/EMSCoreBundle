<?php

namespace Ems\CoreBundle\Controller\ContentManagement;

use Ems\CoreBundle\Controller\AppController;
use Ems\CoreBundle;
use Ems\CoreBundle\Entity\Job;
use Ems\CoreBundle\Form\Form\JobType;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use SensioLabs\AnsiConverter\AnsiToHtmlConverter;
use SensioLabs\AnsiConverter\Theme\Theme;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class JobController extends AppController
{

	/**
	 * @Route("/job/status/{job}", name="job.status"))
	 */
	public function jobStatusAction(Job $job, Request $request)
	{
	
		$theme = new Theme();
		$converter = new AnsiToHtmlConverter($theme);
		
		return $this->render( 'job/status.html.twig', [
				'job' =>  $job,
				'output' => $converter->convert($job->getOutput()),
		] );
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
		
		return $this->render( 'job/add.html.twig', [
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
		/** @var \Ems\CoreBundle\Repository\JobRepository $jobRepository */
		$jobRepository = $manager->getRepository("Ems/CoreBundle:Job");
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
		/** @var \Ems\CoreBundle\Repository\JobRepository $jobRepository */
		$jobRepository = $em->getRepository("Ems/CoreBundle:Job");
		
		$size = $this->container->getParameter('paging_size');
		$from  = ($page-1)*$size;
		$total = $jobRepository->countJobs();
		$lastPage = ceil($total/$size);
		
		
		$jobs = $jobRepository->findBy([], ['created' => 'DESC'], $size, $from);
		
		return $this->render( 'job/index.html.twig', [
				'jobs' =>  $jobs,
				'page' => $page,
				'size' => $size,
				'from' => $from,
				'lastPage' => $lastPage,
				'paginationPath' => 'job.index',
		] );
	}
}