<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle;
use EMS\CoreBundle\Repository\RevisionRepository;
use Doctrine\ORM\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

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
        $repository = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Revision $revision */
//         $revisions = $repository->findBy();
        return $this->render( '@EMSCore/default/coming-soon.html.twig');
    }
    
    /**
     * @Route("/indexes/detele-orphans", name="ems_delete_ophean_indexes")
     * @Method({"POST"})
     */
    public function deleteOphansIndexesAction()
    {
        $client = $this->getElasticsearch();
        foreach ($this->getAliasService()->getOrphanIndexes() as $index) {
            try {
                $client->indices()->delete([
                    'index' => $index['name'],
                ]);
                $this->addFlash('notice', 'Elasticsearch index '.$index['name'].' has been deleted');
            }
            catch (Missing404Exception $e){
                $this->addFlash('warning', 'Elasticsearch index not found');
            }            
        }
        return $this->redirectToRoute('ems_environment_index');
    }
    
}