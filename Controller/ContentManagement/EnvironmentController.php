<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use EMS\CoreBundle;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\ContentTypeFilter;
use EMS\CoreBundle\Entity\Form\RebuildIndex;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Field\ColorPickerType;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Form\CompareEnvironmentFormType;
use EMS\CoreBundle\Form\Form\EditEnvironmentType;
use EMS\CoreBundle\Form\Form\RebuildIndexType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\EnvironmentService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EnvironmentController extends AppController {

	/**
	 *
	 * @Route("/publisher/align", name="environment.align"))
     * @Security("has_role('ROLE_PUBLISHER')")
	 */
	public function alignAction(Request $request) {
		
		$data = [];
		$env = [];
		$withEnvi = [];
				
		/**@var EnvironmentService $environmentService*/
		$environmentService = $this->getEnvironmentService();
		/** @var Client $client */
		$client = $this->getElasticsearch();
		
		$form = $this->createForm(CompareEnvironmentFormType::class, $data, [
		]);

		$form->handleRequest($request);	
		$paging_size = $this->getParameter('ems_core.paging_size');
 		
		if ($form->isSubmitted() && $form->isValid()) {
			$data = $form->getData();
			if($data['environment'] == $data['withEnvironment']){
				$form->addError(new FormError("Source and target environments must be different"));
			}
			else {
				if(array_key_exists('alignWith', $request->request->get('compare_environment_form'))){
					$alignTo = [];
					$alignTo[$request->query->get('withEnvironment')] = $request->query->get('withEnvironment');
					$alignTo[$request->query->get('environment')] = $request->query->get('environment');
					$revid = $request->request->get('compare_environment_form')['alignWith'];

					/** @var EntityManager $em */
					$em = $this->getDoctrine()->getManager();
					
					$repository = $em->getRepository('EMSCoreBundle:Revision');
					
					/**@var Revision $revision */
					$revision = $repository->findOneBy([
							'id' => $revid
					]);
					
					foreach ($revision->getEnvironments() as $item) {
						if(array_key_exists($item->getName(), $alignTo)){
							unset($alignTo[$item->getName()]);
						}
					}

					$continue = true;
					foreach ($alignTo as $env){
						if($revision->getContentType()->getEnvironment()->getName() == $env) {
							$this->session->getFlashBag()->add('warning', 'You can not align the default environment for '.$type.':'.$ouuid);
							$continue = false;
							break;
						}						
					}
					if($continue) {
						$this->getDataService()->lockRevision($revision);
						foreach ($alignTo as $env){
							$item = $repository->findByOuuidContentTypeAndEnvironnement($revision, $environmentService->getAliasByName($env));
							if ($item){
								$this->getDataService()->lockRevision($item);
								$item->removeEnvironment($environmentService->getAliasByName($env));
								$em->persist($item);
							}
							$revision->addEnvironment($environmentService->getAliasByName($env));
							$status = $client->index([
									'id' => $revision->getOuuid(),
									'index' => $environmentService->getAliasByName($env)->getAlias(),
									'type' => $revision->getContentType()->getName(),
									'body' => $this->getDataService()->sign($revision),
							]);
							
						}
						
						$em->persist($revision);
						$em->flush();
	
						foreach ($alignTo as $env){
							$this->addFlash('notice','Revision '.$revid.' of the object '.$revision->getOuuid().' has been published in '.$env);						
						}
					}
				}
				else if(array_key_exists('alignLeft', $request->request->get('compare_environment_form'))) {
					foreach ($request->request->get('compare_environment_form')['item_to_align'] as $item){
						$exploded = explode(':', $item);
						if(count($exploded) == 2){
							$this->getPublishService()->alignRevision($exploded[0], $exploded[1], $request->query->get('withEnvironment'), $request->query->get('environment'));					
						}
						else{
							$this->addFlash('warning', 'Malformed OUUID: '.$item);
						}
					}
				}
				else if(array_key_exists('alignRight', $request->request->get('compare_environment_form'))) {
					foreach ($request->request->get('compare_environment_form')['item_to_align'] as $item){
						$exploded = explode(':', $item);
						if(count($exploded) == 2){
							$this->getPublishService()->alignRevision($exploded[0], $exploded[1], $request->query->get('environment'), $request->query->get('withEnvironment'));					
						}
						else{
							$this->addFlash('warning', 'Malformed OUUID: '.$item);
						}
					}
				}
				else if(array_key_exists('compare', $request->request->get('compare_environment_form'))) {
					$request->query->set('environment', $data['environment']);
					$request->query->set('withEnvironment', $data['withEnvironment']);
					$request->query->set('contentTypes', $data['contentTypes']);
					$request->query->set('page', 1);					
				}
				
				return $this->redirectToRoute('environment.align', $request->query->all());
			}
		}

		if(null != $request->query->get('page')){
			$page = $request->query->get('page');
		}
		else{
			$page = 1;
		}
		
		
		
		$contentTypes = $request->query->get('contentTypes', []);
		if (!$form->isSubmitted()){
			$form->get('contentTypes')->setData($contentTypes);
		}
		if(empty($contentTypes)){
			$contentTypes = $form->get('contentTypes')->getConfig()->getOption('choices', []);
		}
		
		$orderField = $request->query->get('orderField', "contenttype");
		$orderDirection = $request->query->get('orderDirection', 'asc');
		
		if(null != $request->query->get('environment')){
			$environment = $request->query->get('environment');
			if (!$form->isSubmitted()){
				$form->get('environment')->setData($environment);
			}
		}
		else{
			$environment = false;
		}
		
		if(null != $request->query->get('withEnvironment')){
			$withEnvironment = $request->query->get('withEnvironment');

			if (!$form->isSubmitted()){
				$form->get('withEnvironment')->setData($withEnvironment);
			}
		}
		else{
			$withEnvironment = false;
		}
		
		if($environment && $withEnvironment){
			
			/** @var EntityManager $em */
			$em = $this->getDoctrine ()->getManager ();
			/**@var RevisionRepository $repository*/
			$repository = $em->getRepository ( 'EMSCoreBundle:Revision' );
			

			$env = $environmentService->getAliasByName($environment);
			$withEnvi = $environmentService->getAliasByName($withEnvironment);

			$total = $repository->countDifferencesBetweenEnvironment($env->getId(), $withEnvi->getId(), $contentTypes);
			if($total){
				$contentTypeService = $this->getContentTypeService();
				$lastPage = ceil($total/$paging_size);
				if($page > $lastPage){
					$page = $lastPage;
				}
				$results = $repository->compareEnvironment($env->getId(), 
															$withEnvi->getId(), 
															$contentTypes,
															($page-1)*$paging_size, 
															$paging_size,
															$orderField,
															$orderDirection);
				for ($index = 0; $index < count($results); $index++) {
					$results[$index]['contentType'] = $contentTypeService->getByName($results[$index]['content_type_name']);
// 					$results[$index]['revisionEnvironment'] = $repository->findOneById($results[$index]['rId']);
//TODO: is it the better options? to concatenate and split things?
					$minrevid = explode("/", $results[$index]['minrevid']);//1/81522/2017-03-08 14:32:52 => e.id/r.id/r.created
					$maxrevid = explode("/", $results[$index]['maxrevid']);
					if($minrevid[0] == $env->getId()){
						$results[$index]['revisionEnvironment'] = $repository->findOneById($minrevid[1]);
						$results[$index]['revisionWithEnvironment'] = $repository->findOneById($maxrevid[1]);
					} else {
						$results[$index]['revisionEnvironment'] = $repository->findOneById($maxrevid[1]);
						$results[$index]['revisionWithEnvironment'] = $repository->findOneById($minrevid[1]);
					}
					try{
						$results[$index]['objectEnvironment'] = $client->get([
										'id' => $results[$index]['ouuid'],
										'index' => $env->getAlias(),
										'type' => $results[$index]['content_type_name'],
								]);
					}
					catch (Missing404Exception $e){
						$results[$index]['objectEnvironment'] = null;//This revision doesn't exist in this environment, but it's ok.
					}
					try{
						$results[$index]['objectWithEnvironment'] = $client->get([
								'id' => $results[$index]['ouuid'],
								'index' => $withEnvi->getAlias(),
								'type' => $results[$index]['content_type_name'],
						]);
					}
					catch (Missing404Exception $e){
						$results[$index]['objectWithEnvironment'] = null;//This revision doesn't exist in this environment, but it's ok.
					}
				}
			}
			else {
				$page = $lastPage = 1;
				$this->addFlash('notice', 'Those environments are aligned for these content types');
				$total = 0;
				$results = [];
			}
		}
		else {
			$environment = false; 
			$withEnvironment = false;
			$results = false;
			$page = 0;
			$total = 0;
			$lastPage = 0;
		}
		
		return $this->render ( 'EMSCoreBundle:environment:align.html.twig', [
				'form' => $form->createView(),
         		'results' => $results,
				'lastPage' => $lastPage,
				'paginationPath' => 'environment.align',
				'page' => $page,
				'paging_size' => $paging_size,
				'total' => $total,
				'currentFilters' => $request->query,
				'fromEnv' => $env,
				'withEnv' => $withEnvi,
				'environment' => $environment,
				'withEnvironment' => $withEnvironment,
				'environments' => $environmentService->getAll(),
         		'orderField' => $orderField,
         		'orderDirection' => $orderDirection,
				'contentTypes' => $this->getContentTypeService()->getAll(),
         ] );
	}
	
	
	/**
	 * Attach a external index as a new referenced environment
	 *
	 * @param string $name
	 *        	alias name
	 * @param Request $request
	 * 
	 * @Route("/environment/attach/{name}", name="environment.attach"))
     * @Method({"POST"})
	 */
	public function attachAction($name, Request $request) {
		/** @var  Client $client */
		$client = $this->getElasticsearch();
		try {
			$indexes = $client->indices ()->get ( [ 
					'index' => $name 
			] );
			if (strcmp ( $name, array_keys ( $indexes ) [0] ) != 0) {
				/** @var EntityManager $em */
				$em = $this->getDoctrine ()->getManager ();
				
				$environmetRepository = $em->getRepository ( 'EMSCoreBundle:Environment' );
				$anotherObject = $environmetRepository->findBy ( [ 
						'name' => $name 
				] );
				
				if (count ( $anotherObject ) == 0) {
					$environment = new Environment ();
					$environment->setName ( $name );
					$environment->setAlias ( $name );
					//TODO: setCircles
					$environment->setManaged ( false );
					
					$em->persist ( $environment );
					$em->flush ();
					
					$this->addFlash ( 'notice', 'Alias ' . $name . ' has been attached.' );
					
					return $this->redirectToRoute ( 'environment.edit', [ 
							'id' => $environment->getId () 
					] );
				}
			}
		} catch ( Missing404Exception $e ) {
			$this->addFlash ( 'error', 'Something went wrong with Elasticsearch: ' . $e->getMessage () . '!' );
		}
		
		return $this->redirectToRoute ( 'environment.index' );
	}
	
	/**
	 * Try to remove an evironment if it is empty form an eMS perspective.
	 * If it's managed environment the Elasticsearch alias will be also removed.
	 *
	 * @param integer $id        	
	 * @param Request $request
	 * 
	 * @Route("/environment/remove/{id}", name="environment.remove"))
     * @Method({"POST"})
	 *        	
	 */
	public function removeAction($id, Request $request) {
		/** @var EntityManager $em */
		$em = $this->getDoctrine ()->getManager ();
		/** @var  EnvironmentRepository $repository */
		$repository = $em->getRepository ( 'EMSCoreBundle:Environment' );
		/** @var  Environment $environment */
		$environment = $repository->find ( $id );
			
		/** @var  Client $client */
		$client = $this->getElasticsearch();
		if ($environment->getManaged ()) {
			try {
				$indexes = $client->indices ()->get ( [ 
						'index' => $environment->getAlias () 
				] );
				$client->indices ()->deleteAlias ( [ 
						'name' => $environment->getAlias (),
						'index' => array_keys ( $indexes ) [0] 
				] );
			} catch ( Missing404Exception $e ) {
				$this->addFlash ( 'warning', 'Alias ' . $environment->getAlias () . ' not found in Elasticsearch' );
			}
		}
		if ($environment->getRevisions ()->count () != 0) {
			$this->addFlash ( 'error', 'The environement ' . $environment->getName () . ' is not empty.' );
		} else {
			$linked = false;
			/**@var ContentType $contentType */
			foreach ($environment->getContentTypesHavingThisAsDefault() as $contentType){
				if(!$contentType->getDeleted()){
					$linked = true;
					break;
				}
			}
			
			if($linked){
				$this->addFlash ( 'error', 'At least one content type have the environment ' . $environment->getName () . ' has default environment.' );
			}
			else {
				/**@var ContentType $contentType */
				foreach ($environment->getContentTypesHavingThisAsDefault() as $contentType){
					$contentType->getFieldType()->setContentType(null);
					$em->persist($contentType->getFieldType());
					$em->flush ();
					$em->remove($contentType);
					$em->flush ();
				}				
				$em->remove ( $environment );
				$em->flush ();				
				$this->addFlash ( 'notice', 'The environment '.$environment->getName().' has been removed' );
			}
			
		}
		
		return $this->redirectToRoute ( 'environment.index' );
	}
	
	
	public static function isValidName($name) {
		return preg_match('/^[a-z][a-z0-9\-_]*$/', $name) && strlen($name) <= 100;
	}
	
	/**
	 * Add a new environement
	 *
	 * @param Request $request
	 *        	@Route("/environment/add", name="environment.add"))
	 */
	public function addAction(Request $request) {
		$environment = new Environment ();
		
		$form = $this->createFormBuilder ( $environment )->add ( 'name', IconTextType::class, [ 
				'icon' => 'fa fa-database',
				'required' => false 
		] )->add ( 'color', ColorPickerType::class, [ 
				'required' => false 
		] )->add ( 'save', SubmitEmsType::class, [ 
				'label' => 'Create',
				'icon' => 'fa fa-plus',
				'attr' => [ 
						'class' => 'btn btn-primary pull-right' 
				] 
		] )->getForm ();
		
		$form->handleRequest ( $request );
		
		if ($form->isSubmitted ()) {
			
			
			/** @var Environment $environment */
			$environment = $form->getData ();
			if(!$this->isValidName($environment->getName())) {
				$form->get('name')->addError(new FormError('Must respects the following regex /^[a-z][a-z0-9\-_]*$/'));
			}
			
			if($form->isValid ()){
				
				
				/** @var EntityManager $em */
				$em = $this->getDoctrine ()->getManager ();
				
				$environmetRepository = $em->getRepository ( 'EMSCoreBundle:Environment' );
				$anotherObject = $environmetRepository->findBy ( [ 
						'name' => $environment->getName () 
				] );
				
				if (count ( $anotherObject ) != 0) {
					//TODO: test name format
					$form->get ( 'name' )->addError ( new FormError ( 'Another environment named ' . $environment->getName () . ' already exists' ) );
				} else {
					$environment->setAlias ( $this->getParameter ( 'ems_core.instance_id' ) . $environment->getName () );
					$environment->setManaged ( true );
					$em = $this->getDoctrine ()->getManager ();
					$em->persist ( $environment );
					$em->flush ();
	
					$indexName = $environment->getAlias().AppController::getFormatedTimestamp();
					$this->getElasticsearch()->indices()->create([
							'index' => $indexName,
							'body' => $this->getEnvironmentService()->getIndexAnalysisConfiguration(),
					]);
					
					foreach ($this->getContentTypeService()->getAll() as $contentType){
						$this->getContentTypeService()->updateMapping($contentType, $indexName);				
					}
					
					$this->getElasticsearch()->indices()->putAlias([
	    				'index' => $indexName,
	    				'name' => $environment->getAlias()
	    			]);
					
					$this->addFlash('notice', 'A new environement '.$environment->getName().' has been created');
					return $this->redirectToRoute ( 'environment.index' );
					
				}
			}
		}
		
		return $this->render ( 'EMSCoreBundle:environment:add.html.twig', [ 
				'form' => $form->createView () 
		] );
	}
	




	/**
	 * Edit environement (name and color). It's not allowed to update the elasticsearch alias.
	 * @param unknown $id
	 * @param Request $request
	 * @throws NotFoundHttpException
	 * @Route("/environment/edit/{id}", name="environment.edit"))
	 */
	public function editAction($id, Request $request)
	{
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
	
		/** @var EnvironmentRepository $repository */
		$repository = $em->getRepository('EMSCoreBundle:Environment');
	
		/** @var Environment $environment */
		$environment = $repository->find($id);
	
		if(! $environment || count($environment) != 1){
			throw new NotFoundHttpException('Unknow environment');
		}
	
		$options= [];
		if ($this->getParameter("ems_core.circles_object")){
			$options['type'] = $this->getParameter("ems_core.circles_object");
		}
		
		$form = $this->createForm(EditEnvironmentType::class, $environment, $options);
	
		$form->handleRequest($request);
	
		if ($form->isSubmitted() && $form->isValid()) {
			$em->persist($environment);
			$em->flush();
			$this->addFlash('notice', 'Environment '.$environment->getName().' has been updated');
			return $this->redirectToRoute('environment.index');
		}
	
		return $this->render( 'EMSCoreBundle:environment:edit.html.twig',[
				'environment' => $environment,
				'form' => $form->createView(),
		]);
	
	}


	/**
	 * View environement details (especially the mapping information).
	 * @param integer $id 
	 * @param Request $request
	 * @throws NotFoundHttpException
	 * @Route("/environment/{id}", name="environment.view"))
	 */
	public function viewAction($id, Request $request)
	{
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		
		/** @var EnvironmentRepository $repository */
		$repository = $em->getRepository('EMSCoreBundle:Environment');
		
		/** @var Environment $environment */
		$environment = $repository->find($id);
	
		if(! $environment || count($environment) != 1){
			throw new NotFoundHttpException('Unknow environment');
		}

		/** @var  Client $client */
		$client = $this->getElasticsearch();
		
		/** @var ContentTypeRepository $contentTypeRep */
		$contentTypeRep = $em->getRepository('EMSCoreBundle:ContentType');
		
		
		try{
			$info = $client->indices()->getMapping([
					'index' => $environment->getAlias(),
			]);		
		}
		catch (Missing404Exception $e){
			$this->addFlash('error', 'Elasticsearch alias '.$environment->getAlias().' is missing. Consider to rebuild the indexes.');
			$info = false;
		}
	
		return $this->render( 'EMSCoreBundle:environment:view.html.twig',[
				'environment' => $environment,
				'info' => $info,
		]);
	
	}
	
	/**
	 * Go throw all objects defined for a specfic environement and republish them into the index correspond to the environment.
	 * 
	 * @param Environment $environment
	 * @param unknown $alias
	 */
	private function reindexAll(Environment $environment, $alias){
		
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		/** @var  Client $client */
		$client = $this->getElasticsearch();
		/** @var \EMS\CoreBundle\Entity\Revision $revision */
		foreach ($environment->getRevisions() as $revision) {
			if(!$revision->getDeleted()){
				$objectArray = $this->get('ems.service.mapping')->dataFieldToArray ($revision->getDataField());
				$revision->setRawData($objectArray);
				$status = $client->index([
						'index' => $alias,
						'id' => $revision->getOuuid(),
						'type' => $revision->getContentType()->getName(),
						'body' => $this->getDataService()->sign($revision)
				]);		
				$em->persist($revision);
				$em->flush();
			}
		}
			
		$this->addFlash('notice', count($environment->getRevisions()).' objects have been reindexed in '.$alias);
	}
	
	
	/**
	 * Rebuils a environement in elasticsearch in a new index or not (depending the rebuild option)
	 * 
	 * @param integer $id
	 * @param Request $request
	 * @throws NotFoundHttpException
	 * @Route("/environment/rebuild/{id}", name="environment.rebuild"))
	 */
	public function rebuild($id, Request $request)
	{
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		/** @var EnvironmentRepository $repository */
		$repository = $em->getRepository('EMSCoreBundle:Environment');
	
		/** @var Environment $environment */
		$environment = $repository->find($id);
	
		if(! $environment || count($environment) != 1){
			throw new NotFoundHttpException('Unknow environment');
		}
	
		$rebuildIndex = new RebuildIndex();
	
		$form = $this->createForm(RebuildIndexType::class, $rebuildIndex);
	
		$form->handleRequest($request);
	
		if ($form->isSubmitted() && $form->isValid()) {
			$option = $rebuildIndex->getOption();

			switch ($option){
				case "newIndex":
					return $this->startJob('ems.environment.rebuild', [
							'name'    => $environment->getName(),
					]);
				case "sameIndex":
					return $this->startJob('ems.environment.reindex', [
							'name'    => $environment->getName(),
					]);
				default:
					$this->addFlash('warning', 'Unknow rebuild option: '.$option.'.');
			}

		}
	
		return $this->render( 'EMSCoreBundle:environment:rebuild.html.twig',[
				'environment' => $environment,
				'form' => $form->createView(),
		]);
	
	}

	/**
	 * List all environments, orphean indexes, unmanaged aliases and referenced environments
	 * 
	 * @param Request $request
	 * @Route("/environment", name="environment.index"))
	 */
	public function indexAction(Request $request)
	{
		try{
			/** @var EntityManager $em */
			$em = $this->getDoctrine()->getManager();
			/** @var EnvironmentRepository $repository */
			$repository = $em->getRepository('EMSCoreBundle:Environment');
		
			$client = $this->getElasticsearch();
			
			$logger = $this->getLogger();
		
		
			$temp = [];
			$orphanIndexes = [];
		
			$logger->addDebug('For each aliases: start');
			foreach ($client->indices()->getAliases() as $index => $aliases) {
				if(count($aliases["aliases"]) == 0 && strcmp($index{0}, '.') != 0 ){
					$orphanIndexes[] = [
							'name'=> $index,
							'total' => $client->count(['index'=>$index])["count"]
		
					];
				}
				foreach ($aliases["aliases"] as $alias => $other) {
					$temp[$alias] = $index;
				}
					
			}
			$logger->addDebug('For each aliases: done');
		

			$logger->addDebug('For each environments: start');


			$environments = [];//$repository->findAll();
			$stats = $this->getEnvironmentService()->getEnvironmentsStats();
			/** @var  Environment $environment */
			foreach ($stats as $stat) {
				$environment = $stat['environment'];
				$environment->setCounter($stat['counter']);
				$environment->setDeletedRevision($stat['deleted']);
				if(isset($temp[$environment->getAlias()])){
					$environment->setIndex($temp[$environment->getAlias()]);
					$environment->setTotal($client->count(['index'=>$environment->getAlias()])["count"]);
					unset($temp[$environment->getAlias()]);
				}
				$environments[] = $environment;
			}
			$unmanagedIndexes = [];
			foreach ($temp as $alias => $index){
				$unmanagedIndexes[] = [
						'index' => $index,
						'name' => $alias,
						'total' => $client->count(['index'=>$index])["count"],
				];
			}
			$logger->addDebug('For each environments: done');
		
			return $this->render( 'EMSCoreBundle:environment:index.html.twig', [
					'environments' => $environments,
					'orphanIndexes' => $orphanIndexes,
					'unmanagedIndexes' => $unmanagedIndexes,
			]);
		}
		catch (\Elasticsearch\Common\Exceptions\NoNodesAvailableException $e){
			return $this->redirectToRoute('elasticsearch.status');
		}
	}
	
	/**
	 * Update the alias of an environement to a new index
	 * 
	 * @param Client $client
	 * @param string $alias
	 * @param string $to
	 */
	private function switchAlias(Client $client, $alias, $to, $newEnv=false){
		try{
			
			sleep(2);
			$result = $client->indices()->getAlias(['name' => $alias]);
			$index = array_keys ( $result ) [0];
			$params ['body'] = [ 
					'actions' => [ 
							[ 
									'remove' => [ 
											'index' => $index,
											'alias' => $alias 
									],
									'add' => [ 
											'index' => $to,
											'alias' => $alias 
									] 
							] 
					] 
			];
			$client->indices ()->updateAliases ( $params );
		}
		catch(Missing404Exception $e){
			if(!$newEnv){
				$this->addFlash ( 'warning', 'Alias '.$alias.' not found' );				
			}
			$client->indices()->putAlias([
					'index' => $to,
					'name' => $alias
			]);
		}

	}
	
	private function getContentTypesList($contentTypeFilter) {
		$contentTypeList = "";
		if(is_object($contentTypeFilter['contentType'])) {
			$elements = $contentTypeFilter['contentType']->toArray();
		} else if(is_array($contentTypeFilter['contentType'])){
			$elements = $contentTypeFilter['contentType'];
		}
		if(isset($elements)){
			$elementList = [];
			foreach ($elements as $element) {
				$elementList[] = $element->getId().":".$element->getName();
			}
			if(!empty($elementList)){
				$contentTypeList = implode(",",$elementList);
			}
		}
		return $contentTypeList;
	}
	
}