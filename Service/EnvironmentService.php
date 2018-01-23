<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Elasticsearch\Client;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\FilterRepository;
use EMS\CoreBundle\Entity\Filter;
use EMS\CoreBundle\Repository\AnalyzerRepository;
use EMS\CoreBundle\Entity\Analyzer;

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
        
        /**
         * @var Elasticsearch\Client 
         */
        private $client;
	
	public function __construct(Registry $doctrine, Session $session, UserService $userService, AuthorizationCheckerInterface $authorizationChecker, Client $client)
	{
		$this->doctrine = $doctrine;
		$this->session = $session;
		$this->userService = $userService;
		$this->authorizationChecker = $authorizationChecker;
		$this->environments = false;
		$this->byId = false;
                $this->client = $client;
	}
	
	public function getIndexAnalysisConfiguration(){
		$filters = [];
		
		/**@var FilterRepository $filterRepository*/
		$filterRepository= $this->doctrine->getRepository('EMSCoreBundle:Filter');
		/**@var Filter $filter*/
		foreach ($filterRepository->findAll() as $filter) {
			$filters[$filter->getName()] = $filter->getOptions();
		}
		
		$analyzers = [];
		
		/**@var AnalyzerRepository $analyzerRepository*/
		$analyzerRepository= $this->doctrine->getRepository('EMSCoreBundle:Analyzer');
		/**@var Analyzer $analyzer*/
		foreach ($analyzerRepository->findAll() as $analyzer) {
			$analyzers[$analyzer->getName()] = $analyzer->getOptions();
		}
		
		
		
		$out = [
			'index' => [
				'max_result_window' => 	50000,
				'analysis' => [
					'filter' => $filters,
					'analyzer' => $analyzers,
				]
			]
		];
		
		return $out;
		
		
// 		return '{
// 		   "index" : {
//     		  "max_result_window" : 50000,
// 		      "analysis" : {
// 		         "analyzer" : {
// 		            "for_all_field" : {
// 		               "char_filter" : [
// 		                  "html_strip"
// 		               ],
// 		               "tokenizer" : "standard"
// 		            }
// 		         }
// 		      }
// 		   }
// 		}';
	}
	
	public function getEnvironmentsStats() {
		/**@var EnvironmentRepository $repo*/
		$repo = $this->doctrine->getManager()->getRepository('EMSCoreBundle:Environment');
		return $repo->getEnvironmentsStats();
	}
	
	private function loadEnvironment(){
		if($this->environments === false) {
			$environments = $this->doctrine->getManager()->getRepository('EMSCoreBundle:Environment')->findAll();
			$this->environments = [];
			$this->byId = [];
			/**@var \EMS\CoreBundle\Entity\Environment $environment */
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
        
        /**
         * @return array
         */
        public function getExternalEnvironments()
        {
            $this->loadEnvironment();
            $out = [];
		
            foreach ($this->environments as $index => $environment) {
                /* @var $environment Environment */
                if($environment->getManaged() ) {
                   continue;
                }
                
                $alias = $environment->getAlias();
                $indices = $this->client->indices()->getAlias(['index' => $alias]);
                
                $environment->setIndexes(array_keys($indices));
                
                $out[$index] = $environment;
            }
            
            return $out;
        }

	/**
	 * 
	 * @return boolean|array
	 */
	public function getAll(){
		$this->loadEnvironment();
		return $this->environments;
	}
	
	public function getAllInMyCircle() {
		$this->loadEnvironment();
		$out = [];
		$user = $this->userService->getCurrentUser();
		$isAdmin = $this->authorizationChecker->isGranted('ROLE_ADMIN');
		/**@var \EMS\CoreBundle\Entity\Environment $environment*/
		foreach ($this->environments as $index => $environment){
			if( empty($environment->getCircles()) || $isAdmin || !empty(array_intersect($user->getCircles(), $environment->getCircles()))) {
				$out[$index] = $environment;
			}
		}
		return $out;
	}
	
	
}