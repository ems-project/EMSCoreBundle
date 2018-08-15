<?php

namespace EMS\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Cache\Simple\FilesystemCache;

class TwigElementsController extends AppController
{
    const ASSET_EXTRACTOR_STATUS_CACHE_ID = 'status.asset_extractor.result';

    public function sideMenuAction()
    {
    	$draftCounterGroupedByContentType = [];

    	/** @var \EMS\CoreBundle\Repository\RevisionRepository $revisionRepository */
    	$revisionRepository = $this->getDoctrine()->getRepository('EMSCoreBundle:Revision');
    	 
    	$temp = $revisionRepository->draftCounterGroupedByContentType($this->get('ems.service.user')->getCurrentUser()->getCircles(), $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'));
    	foreach ($temp as $item){
    		$draftCounterGroupedByContentType[$item["content_type_id"]] = $item["counter"];
    	}

    	try{ 
	    	$status = $this->getElasticsearch()->cluster()->health()['status'];
    	}
	    catch (\Exception $e){
	    	$status = 'red';
	    }
	    
	    if($status == 'green') {
	    	try {
                $cache = new FilesystemCache();
                if (!$cache->has(TwigElementsController::ASSET_EXTRACTOR_STATUS_CACHE_ID)) {
                    $result = $this->getAssetExtractorService()->hello();
                    $cache->set(TwigElementsController::ASSET_EXTRACTOR_STATUS_CACHE_ID, $result, 600);
                } else {
                    $result = $cache->get(TwigElementsController::ASSET_EXTRACTOR_STATUS_CACHE_ID);
                }

		    	if($result && 200 != $result['code'])
		    	{
		    		$status = 'yellow';
		    	}	    		
	    	}
	    	catch (\Exception $e) {
	    		$status = 'yellow';
	    	}
	    }
    	
    	return $this->render(
    		'@EMSCore/elements/side-menu.html.twig', [
    				'draftCounterGroupedByContentType' => $draftCounterGroupedByContentType,
    				'status' => $status,
    	]);
    }
}
