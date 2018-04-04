<?php

namespace EMS\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class TwigElementsController extends AppController
{
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
		    	$result = $this->getAssetExtractorService()->hello();
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
