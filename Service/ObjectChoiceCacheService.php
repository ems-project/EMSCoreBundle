<?php

namespace EMS\CoreBundle\Service;


use Elasticsearch\Client;
use Symfony\Component\HttpFoundation\Session\Session;
use EMS\CoreBundle\Form\Field\ObjectChoiceListItem;


class ObjectChoiceCacheService
{
	/**@Client $client*/
	private $client;
	/**@var Session $session*/
	private $session;
	/**@var ContentTypeService $contentTypeService*/
	private $contentTypeService;
	
	private $fullyLoaded;
	private $cache;
	
	public function __construct(Client $client, Session $session, ContentTypeService $contentTypeService) {
		$this->client = $client;
		$this->session = $session;
		$this->contentTypeService = $contentTypeService;
		
		$this->fullyLoaded = [];
		$this->cache = [];
	}
	

	public function loadAll($types) {
		$aliasTypes = [];
		$out = [];
		
		$cts = explode(',', $types);
		$body = [];
		foreach ($cts as $type) {
			if(!isset($this->fullyLoaded[$type])){
				$currentType = $this->contentTypeService->getByName($type);
				if($currentType){
					if(!isset($aliasTypes[$currentType->getEnvironment()->getAlias()])){
						$aliasTypes[$currentType->getEnvironment()->getAlias()] = [];
					}
					$aliasTypes[$currentType->getEnvironment()->getAlias()][] = $type;
					$params = [
							'size'=>  '500',
							'index'=> $currentType->getEnvironment()->getAlias(),
							'type'=> $type,
					];
					//TODO test si > 500...flashbag
					
					if($currentType->getOrderField()) {
						$params['body'] = [
							'sort' => [
								$currentType->getOrderField() => [
									'order' => 'asc',
									'missing' => "_last",
								]
							]
						];
					}
					
						
					$items = $this->client->search($params);
						
					foreach ($items['hits']['hits'] as $hit){
						$listItem = new ObjectChoiceListItem($hit, $this->contentTypeService->getByName($hit['_type']));
						$out[$listItem->getValue()] = $listItem;
						$this->cache[$hit['_type']][$hit['_id']] = $listItem;
					}
				}
				else {
					$this->session->getFlashBag()->add('warning', 'ems was not able to find the content type "'.$type.'"');
				}				
				$this->fullyLoaded[$type] = true;
			}
			else {
				foreach ($this->cache[$type] as $id => $item){
					if($item){
						$out[$type.':'.$id] = $item;						
					}
				}
			}
		}
		
		return $out;
	}
	
	public function load($objectIds) {
		$out = [];
		$queries = [];
		foreach ($objectIds as $objectId){
			if(is_string($objectId) && strpos($objectId, ':') !== false){
				$ref = explode(':', $objectId);
				if(!isset($this->cache[$ref[0]])){
					$this->cache[$ref[0]] = [];
				}
				
				if(isset($this->cache[$ref[0]][$ref[1]])){
					if($this->cache[$ref[0]][$ref[1]]) {
						$out[$objectId] = $this->cache[$ref[0]][$ref[1]];						
					}
				}
				else{
					if(!isset($this->fullyLoaded[$ref[0]])){
						$alias = $this->contentTypeService->getByName($ref[0])->getEnvironment()->getAlias();
						if($alias){
							if(!array_key_exists($alias, $queries)){
								$queries[$alias] = ['docs' => []];
							}
							$queries[$alias]['docs'][] = [
								"_type" => $ref[0],
								"_id" => $ref[1],
							];
						}
						else {
							$this->session->getFlashBag()->add('warning', 'ems was not able to find the content type "'.$ref[0].'"');
						}						
					}
				}
				
			}
			else {
				if(null !== $objectId && $objectId !== ""){
					$this->session->getFlashBag()->add('warning', 'ems was not able to parse the object key "'.$objectId.'"');					
				}
			}
		}
		
		foreach ($queries as $alias => $query){
			$params = [
					'index' => $alias,
					'body' => $query
			];
			$result = $this->client->mget($params);
			foreach($result['docs'] as $doc){
				$objectId = $doc['_type'].':'.$doc['_id'];
				if($doc['found']){
					$listItem = new ObjectChoiceListItem($doc, $this->contentTypeService->getByName($doc['_type']));
					$this->cache[$doc['_type']][$doc['_id']] = $listItem;
					$out[$objectId] = $listItem;
				}
				else {
					$this->cache[$doc['_type']][$doc['_id']] = false;
					$this->session->getFlashBag()->add('warning', 'ems was not able to find the object key "'.$objectId.'"');
				}
			}
		}
		return $out;
	}
	
}