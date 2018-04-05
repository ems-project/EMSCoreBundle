<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\HttpFoundation\Session\Session;
use EMS\CoreBundle\Entity\ContentType;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Symfony\Component\Form\FormRegistryInterface;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\FieldType;
use Symfony\Component\Translation\TranslatorInterface;

class ContentTypeService {
	/**@var Registry $doctrine */
	protected $doctrine;
	/**@var Session $session*/
	protected $session;
	
	/**@var Mapping*/
	private $mappingService;
	
	/**@var Client*/
	private $client;
	
	/**@var EnvironmentService $environmentService */
	private $environmentService;
	
	/**@var FormRegistryInterface $formRegistry*/
	private $formRegistry;
	
	/**@var TranslatorInterface $translator*/
	private $translator;
	
	private $instanceId;
	
	protected $orderedContentTypes;
	protected $contentTypeArrayByName;
	
	
	
	public function __construct(Registry $doctrine, Session $session, Mapping $mappingService, Client $client, EnvironmentService $environmentService, FormRegistryInterface $formRegistry, TranslatorInterface $translator, $instanceId)
	{
		$this->doctrine = $doctrine;
		$this->session = $session;
		$this->orderedContentTypes = false;
		$this->contentTypeArrayByName = false;
		$this->mappingService = $mappingService;
		$this->client = $client;
		$this->environmentService = $environmentService;
		$this->formRegistry = $formRegistry;
		$this->instanceId = $instanceId;
		$this->translator= $translator;
	}
	
	private function loadEnvironment(){
		if($this->orderedContentTypes === false) {
			$this->orderedContentTypes = $this->doctrine->getManager()->getRepository('EMSCoreBundle:ContentType')->findBy(['deleted' => false], ['orderKey' => 'ASC']);
			$this->contentTypeArrayByName = [];
			/**@var ContentType $contentType */
			foreach ($this->orderedContentTypes as $contentType) {
				$this->contentTypeArrayByName[$contentType->getName()] = $contentType;
			}
		}
	}
	
	public function persist(ContentType $contentType){
		$em = $this->doctrine->getManager();
		$em->persist($contentType);
		$em->flush();
	}
	
	private function listAllFields(FieldType $fieldType){
		$out = [];
		foreach ($fieldType->getChildren() as $child){
			$out = array_merge($out, $this->listAllFields($child));
		}
		$out['key_'.$fieldType->getId()] = $fieldType;
		return $out;
	}
	
	private function reorderFieldsRecu(FieldType $fieldType, array $newStructure, array $ids){
		
		$fieldType->getChildren()->clear();
		foreach ($newStructure as $key => $item){
			if(array_key_exists('key_'.$item['ouuid'], $ids)){
				$fieldType->getChildren()->add($ids['key_'.$item['ouuid']]);
				$ids['key_'.$item['ouuid']]->setParent($fieldType);
				$ids['key_'.$item['ouuid']]->setOrderKey($key);
				$this->reorderFieldsRecu($ids['key_'.$item['ouuid']], $item['children'], $ids);
			}
			else {
				$this->session->getFlashBag()->add('warning', $this->translator->trans('Field %id% not found and ignored', ['%id%'=>$item['ouuid']]));
			}
		}
	}
	
	public function reorderFields(ContentType $contentType, array $newStructure){
		$em = $this->doctrine->getManager();
		
		$ids = $this->listAllFields($contentType->getFieldType());
		$this->reorderFieldsRecu($contentType->getFieldType(), $newStructure, $ids);
		
		$em->persist($contentType);
		$em->flush();
		
		$this->session->getFlashBag()->add('notice', $this->translator->trans('Content type "%name% has been reordered', ['%name%'=>$contentType->getSingularName()]));
		
	}
	
	private function generatePipeline(FieldType $fieldType) {
		
		$pipelines = [];
		/**@var \EMS\CoreBundle\Entity\FieldType $child */
		foreach ($fieldType->getChildren() as $child) {
			if(!$child->getDeleted()){
				/**@var \EMS\CoreBundle\Form\DataField\DataFieldType $dataFieldType */
				$dataFieldType = $this->formRegistry->getType($child->getType())->getInnerType();
				$pipeline = $dataFieldType->generatePipeline($child);
				if($pipeline) {
					$pipelines[] = $pipeline;
				}
				
				if($dataFieldType->isContainer()) {
					$pipelines = array_merge($pipelines, $this->generatePipeline($child));
				}
			}
		}
		return $pipelines;
	}
	
	public function updateMapping(ContentType $contentType, $envs=false){



		$contentType->setHavePipelines(FALSE);
		try {
			if(!empty($contentType->getFieldType())) {
				$pipelines = $this->generatePipeline($contentType->getFieldType());
				if(!empty($pipelines)) {
					$body = [
							"description" => "Extract attachment information for the content type ".$contentType->getName(),
							"processors" => $pipelines,
					];
					$this->client->ingest()->putPipeline([
							'id' => $this->instanceId.$contentType->getName(),
							'body' => $body
					]);
					$contentType->setHavePipelines(TRUE);
				$this->session->getFlashBag()->add ( 'notice', 'Pipelines updated/created for '.$contentType->getName() );
				}
			}
		} catch ( BadRequest400Exception $e ) {
			$contentType->setHavePipelines( false );
			$message = json_decode($e->getMessage(), true);
			if(!empty($e->getPrevious())){
				$message = json_decode($e->getPrevious()->getMessage(), true);			
			}
			$this->session->getFlashBag()->add ( 'error', '<p><strong>elasticms was not able to generate pipelines, they are disabled</strong> Please consider to update your elasticsearch cluster (>=5.0) and/or install the ingest attachment plugin (bin/elasticsearch-plugin install ingest-attachment)</p>
					<p>Message from Elasticsearch: <b>' . $message['error']['type']. '</b>'.$message['error']['reason'] . '</p>' );
		}
		
		try {	
			
			if(!$envs){
				$envs = array_reduce ( $this->environmentService->getManagedEnvironement(), function ($envs, $item) {
					/**@var \EMS\CoreBundle\Entity\Environment $item*/
					if (isset ( $envs )) {
						$envs .= ',' . $item->getAlias();
					} else {
						$envs = $item->getAlias();
					}
					return $envs;
				} );
			}
			
			$body = $this->mappingService->generateMapping ($contentType, $contentType->getHavePipelines());
			$out = $this->client->indices()->putMapping ( [
					'index' => $envs,
					'type' => $contentType->getName(),
					'body' => $body
			] );
				
			if (isset ( $out ['acknowledged'] ) && $out ['acknowledged']) {
				$contentType->setDirty ( false );
				if($this->session->isStarted()){
					$this->session->getFlashBag()->add ( 'notice', 'Mappings successfully updated/created for '.$contentType->getName().' in '.$envs );
				}
			} else {
				$contentType->setDirty ( true );
				$this->session->getFlashBag()->add ( 'warning', '<p><strong>Something went wrong. Try again</strong></p>
						<p>Message from Elasticsearch: ' . print_r ( $out, true ) . '</p>' );
			}
			
			$em = $this->doctrine->getManager();
			$em->persist($contentType);
			$em->flush();
			
				
		} catch ( BadRequest400Exception $e ) {
			$contentType->setDirty ( true );
			$message = json_decode($e->getMessage(), true);
			if(!empty($e->getPrevious())) {
				$message = json_decode($e->getPrevious()->getMessage(), true);				
			}
			$this->session->getFlashBag()->add ( 'error', '<p><strong>You should try to rebuild the indexes for '.$contentType->getName().'</strong></p>
					<p>Message from Elasticsearch: <b>' . $message['error']['type']. '</b>'.$message['error']['reason'] . '</p>' );
		}
	}
	
	/**
	 * 
	 * @param string $name
	 * @return ContentType
	 */
	public function getByName($name){
		$this->loadEnvironment();
		if(isset($this->contentTypeArrayByName[$name])){
			return $this->contentTypeArrayByName[$name];
		}
		return false;
	}



	/**
	 *
	 * @param string $name
	 * @return ContentType
	 */
	public function getAllByAliases(){
		$this->loadEnvironment();
		$out = [];
		/**@var ContentType $contentType */
		foreach ($this->orderedContentTypes as $contentType){
			if(!isset( $out[$contentType->getEnvironment()->getAlias()] )){
				$out[$contentType->getEnvironment()->getAlias()] = [];
			}
			$out[$contentType->getEnvironment()->getAlias()][$contentType->getName()] = $contentType;
		}
		return $out;
	}
	
	
	/**
	 *
	 */
	public function getAllDefaultEnvironmentNames(){
	    $this->loadEnvironment();
	    $out = [];
	    /**@var ContentType $contentType */
	    foreach ($this->orderedContentTypes as $contentType){
	        if(!isset( $out[$contentType->getEnvironment()->getAlias()] )){
	            $out[$contentType->getEnvironment()->getName()] = $contentType->getEnvironment()->getName();
	        }
	    }
	    return array_keys($out);
	}
	
	/**
	 *
	 */
	public function getAllAliases(){
		$this->loadEnvironment();
		$out = [];
		/**@var ContentType $contentType */
		foreach ($this->orderedContentTypes as $contentType){
			if(!isset( $out[$contentType->getEnvironment()->getAlias()] )){
				$out[$contentType->getEnvironment()->getAlias()] = $contentType->getEnvironment()->getAlias();
			}
		}
		return implode(',', $out);
	}
	/**
	 *
	 */
	public function getAll(){
	    $this->loadEnvironment();
	    return $this->orderedContentTypes;
	}
	/**
	 *
	 */
	public function getAllNames(){
	    $this->loadEnvironment();
	    $out = [];
	    /**@var Environment $env*/
	    foreach ($this->orderedContentTypes as $env){
	        $out[] = $env->getName();
	    }
	    return $out;
	}

	/**
	 * 
	 * @return string
	 */
	 public function getAllTypes(){
		$this->loadEnvironment();
		return implode(',', array_keys($this->contentTypeArrayByName));
	}
	
}