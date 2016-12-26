<?php

namespace Ems\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Ems\CoreBundle\Entity\Environment;
use Ems\CoreBundle\Repository\EnvironmentRepository;

class EnvironmentService {
	/**@var Registry $doctrine */
	private $doctrine;
	/**@var Session $session*/
	private $session;
	
	private $environments;
	
	/**@var UserService $userService*/
	private $userService;
	
	/** @var AuthorizationCheckerInterface $authorizationChecker*/
	private $authorizationChecker;
	
	
	public function __construct(Registry $doctrine, Session $session, UserService $userService, AuthorizationCheckerInterface $authorizationChecker)
	{
		$this->doctrine = $doctrine;
		$this->session = $session;
		$this->userService = $userService;
		$this->authorizationChecker = $authorizationChecker;
		$this->environments = false;
		$this->byId = false;
	}
	
	public function getEnvironmentsStats() {
		/**@var EnvironmentRepository $repo*/
		$repo = $this->doctrine->getManager()->getRepository('Ems/CoreBundle:Environment');
		return $repo->getEnvironmentsStats();
	}
	
	private function loadEnvironment(){
		if($this->environments === false) {
			$environments = $this->doctrine->getManager()->getRepository('Ems/CoreBundle:Environment')->findAll();
			$this->environments = [];
			$this->byId = [];
			/**@var \Ems\CoreBundle\Entity\Environment $environment */
			foreach ($environments as $environment) {
				$this->environments[$environment->getName()] = $environment;
				$this->byId[$environment->getId()] = $environment;
			}
		}
	}
	
	/**
	 * 
	 * @param string $name
	 * @return Environment
	 */
	public function getAliasByName($name){
		return $this->getByName($name);
	}	
	
	/**
	 * 
	 * @param string $name
	 * @return Environment
	 */
	public function getByName($name){
		$this->loadEnvironment();
		if(isset($this->environments[$name])){
			return $this->environments[$name];
		}
		return false;
	}	
	
	public function getById($id){
		$this->loadEnvironment();
		if(isset($this->byId[$id])){
			return $this->byId[$id];
		}
		return false;
	}

	public function getManagedEnvironement(){
		$this->loadEnvironment();
		$out = [];
		
		/**@var Environment $environment*/
		foreach ($this->environments as $index => $environment){
			if( $environment->getManaged() ) {
				$out[$index] = $environment;
			}
		}
		return $out;
	}

	public function getAll(){
		$this->loadEnvironment();
		return $this->environments;
	}
	
	public function getAllInMyCircle() {
		$this->loadEnvironment();
		$out = [];
		$user = $this->userService->getCurrentUser();
		$isAdmin = $this->authorizationChecker->isGranted('ROLE_ADMIN');
		/**@var \Ems\CoreBundle\Entity\Environment $environment*/
		foreach ($this->environments as $index => $environment){
			if( empty($environment->getCircles()) || $isAdmin || !empty(array_intersect($user->getCircles(), $environment->getCircles()))) {
				$out[$index] = $environment;
			}
		}
		return $out;
	}
	
	
}