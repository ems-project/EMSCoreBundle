<?php

namespace EMS\CoreBundle\Service;


use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Form\FieldType\FieldTypeType;


class Mapping
{

	/** @var FieldTypeType $fieldTypeType */
	private $fieldTypeType;
	
	public function __construct(FieldTypeType $fieldTypeType) {
		$this->fieldTypeType = $fieldTypeType;
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
				'_sha1' => [
					'type' => 'string',
					'index' => 'not_analyzed'
				],
				'_signature' => [
					'type' => 'string',
					'index' => 'not_analyzed'
				],
				'_finalized_by' => [
					'type' => 'string',
					'index' => 'not_analyzed'
				],
				'_finalized_datetime' => [
					'type' => 'date',
					'format' => 'date_time_no_millis'
				],
			],
			$out[$contentType->getName()]['properties']
		);
		
		
		
		return $out;
	} 



	public function dataFieldToArray(DataField $dataField){
		return $this->fieldTypeType->dataFieldToArray($dataField);
	}	
	
	
}