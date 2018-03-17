<?php

namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle;
use EMS\CoreBundle\Controller\AppController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;

/**
 * Wysiwyg controller.
 *
 * @Route("/search-options")
 */
class SearchController extends AppController
{
	/**
	 * Lists all Search options.
	 *
	 * @Route("/", name="ems_search_options_index")
	 * @Method({"GET"})
	 */
	public function indexAction(Request $request)
	{
		
		return $this->render('@EMSCore/search-options/index.html.twig', [
				
		]);
	}
}