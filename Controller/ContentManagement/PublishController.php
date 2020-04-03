<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Field\EnvironmentPickerType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Form\SearchFormType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class PublishController extends AppController
{
    /**
     * @param Revision $revisionId
     * @param Environment $envId
     * @return RedirectResponse
     * @Route("/publish/to/{revisionId}/{envId}", name="revision.publish_to"))
     */
    public function publishToAction(Revision $revisionId, Environment $envId)
    {
        $this->getPublishService()->publish($revisionId, $envId);
        
        return $this->redirectToRoute('data.revisions', [
                'ouuid' => $revisionId->getOuuid(),
                'type' => $revisionId->getContentType()->getName(),
                'revisionId' => $revisionId->getId(),
        ]);
    }

    /**
     * @param Revision $revisionId
     * @param Environment $envId
     * @return RedirectResponse
     * @Route("/revision/unpublish/{revisionId}/{envId}", name="revision.unpublish"))
     */
    public function unpublishAction(Revision $revisionId, Environment $envId)
    {
        $this->getPublishService()->unpublish($revisionId, $envId);
        
        return $this->redirectToRoute('data.revisions', [
                'ouuid' => $revisionId->getOuuid(),
                'type' => $revisionId->getContentType()->getName(),
                'revisionId' => $revisionId->getId(),
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse|Response
     * @throws NoResultException
     * @throws NonUniqueResultException
     *
     * @Route("/publish/search-result", name="search.publish", defaults={"deleted": 0, "managed": 1})
     * @Security("has_role('ROLE_PUBLISHER')")
     */
    public function publishSearchResult(Request $request)
    {
        $search = new Search();
        $searchForm = $this->createForm(SearchFormType::class, $search, [
                'method' => 'GET',
        ]);
        $requestBis = clone $request;
        
        $requestBis->setMethod('GET');
        $searchForm->handleRequest($requestBis);
        
        /**@var Environment $environment */
        /**@var ContentType $contentType */
        if (count($search->getEnvironments()) != 1 && $this->getEnvironmentService()->getAliasByName($search->getEnvironments()[0])) {
            throw new NotFoundHttpException('Environment not found');
        }
        if (count($search->getContentTypes()) != 1 && $contentType = $this->getContentTypeService()->getByName($search->getContentTypes()[0])) {
            throw new NotFoundHttpException('Content type not found');
        }
        
        $environment = $this->getEnvironmentService()->getAliasByName($search->getEnvironments()[0]);
        $contentType = $this->getContentTypeService()->getByName($search->getContentTypes()[0]);
        
        $data = [];
        $builder = $this->createFormBuilder($data);
        $builder->add('toEnvironment', EnvironmentPickerType::class, [
            'managedOnly' => true,
            'ignore' => [$environment->getName()],
        ])->add('publish', SubmitEmsType::class, [
                'attr' => [
                        'class' => 'btn-primary btn-md'
                ],
                'icon' => 'glyphicon glyphicon-open'
        ]);
        
        $body = $this->getSearchService()->generateSearchBody($search);
        $form = $builder->getForm();
        $form->handleRequest($request);

        $total = $this->elasticsearchClient->searchByContentType(
            $environment->getAlias(),
            $contentType->getName(),
            $body,
            0
        )->getTotal();

        if ($form->isSubmitted()) {
            $toEnvironment = $this->getEnvironmentService()->getAliasByName($form->get('toEnvironment')->getData());
            $body['sort'] = ['_uid' => 'asc'];
            $scroll = $this->elasticsearchClient->scrollByContentType(
                $environment->getAlias(),
                $contentType->getName(),
                $body,
                50
            );

            foreach ($scroll as $searchResponse) {
                foreach ($searchResponse->getDocumentCollection() as $document) {
                    $revision = $this->getDataService()->getRevisionByEnvironment(
                        $document->getId(),
                        $this->getContentTypeService()->getByName($document->getContentType()),
                        $environment
                    );
                    $this->getPublishService()->publish($revision, $toEnvironment);
                }
            }
            
            return $this->redirectToRoute('elasticsearch.search', $requestBis->query->all());
        }
            
    
        
        
        return $this->render('@EMSCore/publish/publish-search-result.html.twig', [
                'form' => $form->createView(),
                'fromEnvironment' => $environment,
                'contentType' => $contentType,
                'counter' => $total,
        ]);
    }
}
