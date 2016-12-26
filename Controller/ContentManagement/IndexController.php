<?php

namespace Ems\CoreBundle\Controller\ContentManagement;

use Ems\CoreBundle\Controller\AppController;
use Ems\CoreBundle;
use Ems\CoreBundle\Repository\RevisionRepository;
use Doctrine\ORM\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class IndexController extends AppController
{
	/**
	 * @Route("/indexes/content-type/{contentTypeId}/{alias}", name="index.content-type")
	 */
	public function reindexContentTypeAction($contentTypeId, $alias)
	{
		/** @var EntityManager $em */
		$em = $this->getDoctrine()->getManager();
		/** @var RevisionRepository $repository */
		$repository = $em->getRepository('Ems/CoreBundle:Revision');
		/** @var Revision $revision */
// 		$revisions = $repository->findBy();
		
			
			return $this->render( 'default/coming-soon.html.twig');
		
		
	}
}