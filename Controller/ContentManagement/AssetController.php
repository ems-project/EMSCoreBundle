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

		$config = [
				'_identifier' => $processor,
				'_resize' => 'fill',
				'_width' => 300,
				'_quality' => 70,
				'_height' => 200,
				'_gravity' => 'center',
				'_radius' => false,
				'_background' => 'FFFFFF',
				'_radius_geometry' => 'topleft-topright-bottomright-bottomleft',
				'_watermark' => false,
				'_last_update_date' => '1977-02-09T16:00:00+01:00',
				'_config_type' => 'image',
		];

        if($this->getParameter('ems_core.asset_config_type') || $this->getParameter('ems_core.asset_config_index')) {
            try {
                $result = $this->getElasticsearch()->search([
                    'size' => 1,
                    'type' => $this->getParameter('ems_core.asset_config_type'),
                    'index' => $this->getParameter('ems_core.asset_config_index'),
                    'body' => '
                        {
                           "query": {
                              "term": {
                                 "_identifier": {
                                    "value": ' . json_encode($processor) . '
                                 }
                              }
                           }
                        }',
                ]);


                if ($result['hits']['total'] != 0) {
                    $config = $result['hits']['hits'][0]['_source'];
                }
            } catch (\Exception $e) {

            }
        }
		return $this->getAssetService()->getAssetResponse($config, $hash, $request->query->get('type', 'unkown'));
	}
}