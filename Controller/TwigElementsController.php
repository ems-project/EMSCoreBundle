<?php

namespace Ems\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class TwigElementsController extends Controller
{
    public function sideMenuAction()
    {
    	$draftCounterGroupedByContentType = [];

    	/** @var \Ems\CoreBundle\Repository\RevisionRepository $revisionRepository */
    	$revisionRepository = $this->getDoctrine()->getRepository('Ems/CoreBundle:Revision');
    	 
    	$temp = $revisionRepository->draftCounterGroupedByContentType($this->get('ems.service.user')->getCurrentUser()->getCircles(), $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'));
    	foreach ($temp as $item){
    		$draftCounterGroupedByContentType[$item["content_type_id"]] = $item["counter"];
    	}

    	return $this->render(
    		'elements/side-menu.html.twig', [
    			'draftCounterGroupedByContentType' => $draftCounterGroupedByContentType
    	]);
    }
}
