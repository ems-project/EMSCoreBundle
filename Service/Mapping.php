<?php

namespace EMS\CoreBundle\Service;


use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Form\FieldType\FieldTypeType;


class Mapping
{
	
	/** @var FieldTypeType $fieldTypeType */
	private $fieldTypeType;
	
	/** @var ElasticsearchService $elasticsearchService */
	private $elasticsearchService;
	
	/**
	 * Constructor
	 * 
	 * @param FieldTypeType $fieldTypeType
	 * @param ElasticsearchService $elasticsearchService
	 */
	public function __construct(FieldTypeType $fieldTypeType, ElasticsearchService $elasticsearchService) {
		$this->fieldTypeType = $fieldTypeType;
		$this->elasticsearchService = $elasticsearchService;
	}
	
	public function generateMapping(ContentType $contentType, $withPipeline = false){
		$out = [
				$contentType->getName() => [
						"_all" => [
								"store" => true,
								"enabled" => true,
						],
							"properties" => [
						],
				],
		];
		
		if(null != $contentType->getFieldType()){
			$out[$contentType->getName()]['properties'] = $this->fieldTypeType->generateMapping($contentType->getFieldType(), $withPipeline);
		}
		
		$out[$contentType->getName()]['properties'] = array_merge(
			[
				'_sha1' => $this->elasticsearchService->getKeywordMapping(),
				'_signature' => $this->elasticsearchService->getNotIndexedStringMapping(),
				'_finalized_by' => $this->elasticsearchService->getKeywordMapping(),
				'_finalization_datetime' => $this->elasticsearchService->getDateTimeMapping(),
			],
			$out[$contentType->getName()]['properties']
		);
		
		
		
		return $out;
	} 



	public function dataFieldToArray(DataField $dataField){
		return $this->fieldTypeType->dataFieldToArray($dataField);
	}	
	
	
}