<?php

namespace EMS\CoreBundle\Service;


use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\DependencyInjection\EMSCoreExtension;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\DataField;
use EMS\CoreBundle\Form\FieldType\FieldTypeType;


class Mapping
{
	
	/** @var FieldTypeType $fieldTypeType */
	private $fieldTypeType;
	
	/** @var ElasticsearchService $elasticsearchService */
	private $elasticsearchService;

    /**@var string*/
    private $coreVersion;

    /**@var string*/
    private $instanceId;
	
	/**
	 * Constructor
	 * 
	 * @param FieldTypeType $fieldTypeType
	 * @param ElasticsearchService $elasticsearchService
	 */
	public function __construct(FieldTypeType $fieldTypeType, ElasticsearchService $elasticsearchService, $coreVersion, $instanceId) {
		$this->fieldTypeType = $fieldTypeType;
		$this->elasticsearchService = $elasticsearchService;
        $this->coreVersion = $coreVersion;
        $this->instanceId = $instanceId;
	}
	
	public function generateMapping(ContentType $contentType, $withPipeline = false){
		$out = [
		    "properties" => [],
		];

		if($this->elasticsearchService->withAllMapping()) {
            $out['_all'] = [
                "store" => true,
                "enabled" => true,
            ];
        }
		
		if(null != $contentType->getFieldType()){
			$out['properties'] = $this->fieldTypeType->generateMapping($contentType->getFieldType(), $withPipeline);
		}
		
		$out['properties'] = array_merge(
			[
				'_sha1' => $this->elasticsearchService->getKeywordMapping(),
				'_signature' => $this->elasticsearchService->getNotIndexedStringMapping(),
				'_finalized_by' => $this->elasticsearchService->getKeywordMapping(),
				'_finalization_datetime' => $this->elasticsearchService->getDateTimeMapping(),
			],
			$out['properties']
		);

		$out['_meta'] = [
		    'content_type' => $contentType->getName(),
            'generator' => 'elasticms',
            'core_version' => $this->coreVersion,
            'instance_id' => $this->instanceId,
        ];
		
		
		return [ $contentType->getName() => $out ];
	}



	public function dataFieldToArray(DataField $dataField){
		return $this->fieldTypeType->dataFieldToArray($dataField);
	}	
	
	
}