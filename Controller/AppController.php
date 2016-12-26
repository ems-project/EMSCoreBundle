<?php
namespace Ems\CoreBundle\Controller;

use Ems\CoreBundle\Entity\Job;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Ems\CoreBundle\Service\DataService;
use Ems\CoreBundle\Service\PublishService;
use Ems\CoreBundle\Service\ContentTypeService;
use Ems\CoreBundle\Service\EnvironmentService;
use Elasticsearch\Client;
use Ems\CoreBundle\Service\SearchService;
use Monolog\Logger;
use Ems\CoreBundle\Service\NotificationService;
use Ems\CoreBundle\Service\UserService;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

class AppController extends Controller
{
	/**
	 * @Route("/js/app.js", name="app.js"))
	 */
	public function javascriptAction()
	{
		return $this->render( 'app/app.js.twig' );
	}

	/**
	 * @return Client
	 */
	protected function getElasticsearch()
	{
		return $this->get('app.elasticsearch');
	}

	/**
	 * @return AuthorizationChecker
	 */
	protected function getAuthorizationChecker(){
		return $this->get('security.authorization_checker');
	}
	
	/**
	 * 
	 * @return EncoderFactoryInterface
	 */
	protected function getSecurityEncoder()
	{
		return $this->get('security.encoder_factory');
	}
	
	/**
	 * @return UserService
	 */
	protected function getUserService()
	{
		return $this->get('ems.service.user');
	}

	
	/**
	 * @return NotificationService
	 */
	protected function getNotificationService()
	{
		return $this->get('ems.service.notification');
	}
	
	/**
	 * @return \Twig_Environment
	 */
	protected function getTwig()
	{
		return $this->container->get('twig');
	}

	/**
	 * @return SearchService
	 */
	protected function getSearchService()
	{
		return $this->container->get('ems.service.search');
	}
	
	/**
	 * 
	 * @param string $fieldTypeNameOrServiceName
	 * 
	 * @return DataFieldType
	 */
	protected function getDataFielType($fieldTypeNameOrServiceName){
		return $this->get('form.registry')->getType($fieldTypeNameOrServiceName)->getInnerType();
	}
	
	/**
	 * Get the injected logger
	 * 
	 * @return Logger
	 * 
	 */
	protected function getLogger(){
		return $this->get('logger');
	}
	
	

	protected function startJob($service, $arguments){
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		
		$job = new Job();
		$job->setUser($this->getUser()->getUsername());
		$job->setDone(false);
		$job->setArguments($arguments);
		$job->setProgress(0);
		$job->setService($service);
		$job->setStatus("Job intialized");
		$em->persist($job);
		$em->flush();
		
		return $this->redirectToRoute('job.status', [
			'job' => $job->getId(),
		]);
	}
	

	protected function startConsole(Job $job){
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		
		$job->setUser($this->getUser()->getUsername());
		$job->setDone(false);
		$job->setProgress(0);
		$job->setStatus("Job intialized");
		
		$em->persist($job);
		$em->flush();
		
		return $this->redirectToRoute('job.status', [
			'job' => $job->getId(),
		]);
	}

	public static function getFormatedTimestamp(){
		return date('_Ymd_His');
	}
	
	protected function getGUID(){
		mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
		$charid = strtolower(md5(uniqid(rand(), true)));
		$hyphen = chr(45);// "-"
		$uuid = 
		 substr($charid, 0, 8).$hyphen
		.substr($charid, 8, 4).$hyphen
		.substr($charid,12, 4).$hyphen
		.substr($charid,16, 4).$hyphen
		.substr($charid,20,12);
		return $uuid;
	}


	/**
	 *
	 * @return DataService
	 */
	public function getDataService(){
		return $this->get('ems.service.data');
	}
	
	/**
	 * 
	 * @return PublishService
	 */
	public function getPublishService(){
		return $this->get('ems.service.publish');
	}

	/**
	 *
	 * @return ContentTypeService
	 */
	public function getContentTypeService(){
		return $this->get('ems.service.contenttype');
	}
	
	/**
	 * 
	 * @return EnvironmentService
	 */
	public function getEnvironmentService(){
		return $this->get('ems.service.environment');
	}
	
	
}
