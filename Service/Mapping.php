<?php

namespace Ems\CoreBundle\Service;


use Ems\CoreBundle\Entity\ContentType;
use Ems\CoreBundle\Entity\DataField;
use Ems\CoreBundle\Form\FieldType\FieldTypeType;


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
		return $out;
	} 



	public function dataFieldToArray(DataField $dataField){
		return $this->fieldTypeType->dataFieldToArray($dataField);
	}	
	
	
}