<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use EMS\CoreBundle\Controller\AppController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends AppController
{
    /**
     * @return RedirectResponse
     *
     * @Route("/indexes/detele-orphans", name="ems_delete_ophean_indexes", methods={"POST"})
     */
    public function deleteOrphansIndexesAction()
    {
        $client = $this->getElasticsearch();
        foreach ($this->getAliasService()->getOrphanIndexes() as $index) {
            try {
                $client->indices()->delete([
                    'index' => $index['name'],
                ]);
                $this->getLogger()->notice('log.index.delete_orphan_index', [
                    'index_name' => $index['name'],
                ]);
            } catch (Missing404Exception $e) {
                $this->getLogger()->notice('log.index.index_not_found', [
                    'index_name' => $index['name'],
                ]);
            }
        }
        return $this->redirectToRoute('ems_environment_index');
    }
}
