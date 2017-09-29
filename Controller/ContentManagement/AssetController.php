<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle;
use EMS\CoreBundle\Controller\AppController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssetController extends AppController
{
	/**
	 * @Route("/asset/{processor}/{hash}", name="ems_asset_processor")
	 */
	public function assetProcessorAction($processor, $hash, Request $request)
	{
		if(!$this->getParameter('ems_core.asset_config_type') || !$this->getParameter('ems_core.asset_config_type')){
			throw new NotFoundHttpException('Asset processor index and type configuration not found');
		}
		
		$result = $this->getElasticsearch()->search([
				'size' => 1,
				'type' => $this->getParameter('ems_core.asset_config_type'),
				'index' => $this->getParameter('ems_core.asset_config_index'),
				'body' => '
				{
				   "query": {
				      "term": {
				         "identifier": {
				            "value": '.json_encode($processor).'
				         }
				      }
				   }
				}',
		]);
		if($result['hits']['total'] == 0){
			throw new NotFoundHttpException('Asset processor not found');
		}
		return $this->getAssetService()->getAssetResponse($result['hits']['hits'][0]['_source'], $hash);
	}
}