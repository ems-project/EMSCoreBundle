<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle;
use EMS\CoreBundle\Controller\AppController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;


/**
 * @Route("/analyzer")
 * @author Mathieu De Keyzer <ems@theus.be>
 *
 */
class AnalyzerController extends AppController
{
	/**
	 * @Route("/", name="ems_analyzer_index")
	 */
	public function indexAction(Request $request) {
		return $this->render( 'EMSCoreBundle:analyzer:index.html.twig', [
				'paging' => $this->getHelperService()->getPagingTool('EMSCoreBundle:Analyzer', 'ems_analyzer_index', 'name'),
		] );
	}
	
	/**
	 * Creates a new elasticsearch anlyzer entity.
	 *
	 * @Route("/add", name="ems_analyzer_add")
	 * @Method({"GET", "POST"})
	 */
	public function addAction(Request $request)
	{
		return $this->render( 'EMSCoreBundle:analyzer:add.html.twig', [
		] );
		
	}
}
	