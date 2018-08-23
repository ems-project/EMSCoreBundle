<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NoResultException;
use Dompdf\Dompdf;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use EMS\CoreBundle;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Exception\HasNotCircleException;
use EMS\CoreBundle\Exception\PrivilegeException;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\RenderOptionType;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Form\Form\ViewType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\TemplateRepository;
use EMS\CoreBundle\Repository\ViewRepository;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\ContentTypeService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\Form\SearchFilter;

class DataController extends AppController
{

    /**
     * @Route("/data/{name}", name="ems_data_default_search"))
     * @Route("/data/{name}", name="data.root"))
     */
    public function rootAction($name, Request $request)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var ContentTypeRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:ContentType');
        /**@var ContentType $contentType */
        $contentType = $repository->findOneBy([
            'name' => $name,
            'deleted' => false
        ]);

        if (!$contentType) {
            throw NotFoundHttpException('Content type ' . $name . ' not found');
        }


        $searchRepository = $em->getRepository('EMSCoreBundle:Form\Search');
        $searchs = $searchRepository->findBy([
            'contentType' => $contentType->getId(),
        ]);
        /**@var Search $search*/
        foreach ($searchs as $search){

            return $this->forward('EMSCoreBundle:Elasticsearch:search', [
                'query' => null,
            ], [
                'search_form' => $search->jsonSerialize(),
            ]);
        }


        $searchForm = new Search();
        $searchForm->setContentTypes([$contentType->getName()]);
        $searchForm->setEnvironments([$contentType->getEnvironment()->getName()]);
        $searchForm->setSortBy('_uid');
        if ($contentType->getSortBy()) {
            $searchForm->setSortBy($contentType->getSortBy());
        }
        $searchForm->setSortOrder('asc');
        if ($contentType->getSortOrder()) {
            $searchForm->setSortOrder($contentType->getSortOrder());
        }


        return $this->forward('EMSCoreBundle:Elasticsearch:search', [
            'query' => null,
        ], [
            'search_form' => $searchForm->jsonSerialize(),
        ]);
    }


    /**
     * @Route("/data/in-my-circles/{name}", name="ems_search_in_my_circles"))
     */
    public function inMyCirclesAction($name, Request $request)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var ContentTypeRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:ContentType');
        /**@var ContentType $contentType */
        $contentType = $repository->findOneBy([
            'name' => $name,
            'deleted' => false
        ]);

        if (!$contentType) {
            throw NotFoundHttpException('Content type ' . $name . ' not found');
        }

        $searchForm = new Search();
        $searchForm->setContentTypes([$contentType->getName()]);
        $searchForm->setEnvironments([$contentType->getEnvironment()->getName()]);
        $searchForm->setSortBy('_uid');
        if ($contentType->getSortBy()) {
            $searchForm->setSortBy($contentType->getSortBy());
        }
        $searchForm->setSortOrder('asc');
        if ($contentType->getSortOrder()) {
            $searchForm->setSortOrder($contentType->getSortOrder());
        }

        $searchForm->filters = [];
        foreach ($this->getUser()->getCircles() as $cicle) {
            $filter = new SearchFilter();
            $filter->setBooleanClause('should')
                ->setField($contentType->getCirclesField())
                ->setOperator('term')
                ->setPattern($cicle);
            $searchForm->addFilter($filter);
        }

        return $this->forward('EMSCoreBundle:Elasticsearch:search', [
            'query' => null,
        ], [
            'search_form' => json_decode(json_encode($searchForm), true),
        ]);
    }


    /**
     * @Route("/data/trash/{contentType}", name="ems_data_trash"))
     */
    public function trashAction(ContentType $contentType, Request $request)
    {
        return $this->render('@EMSCore/data/trash.html.twig', [
            'contentType' => $contentType,
            'revisions' => $this->getDataService()->getAllDeleted($contentType),
        ]);
    }


    /**
     *
     * @Route("/data/put-back/{contentType}/{ouuid}", name="ems_data_put_back"))
     * @Method({"POST"})
     */
    public function putBackAction(ContentType $contentType, $ouuid, Request $request)
    {
        $revId = $this->getDataService()->putBack($contentType, $ouuid);

        return $this->redirectToRoute('ems_revision_edit', [
            'revisionId' => $revId
        ]);
    }


    /**
     *
     * @Route("/data/empty-trash/{contentType}/{ouuid}", name="ems_data_empty_trash"))
     * @Method({"POST"})
     */
    public function emptyTrashAction(ContentType $contentType, $ouuid, Request $request)
    {
        $this->getDataService()->emptyTrash($contentType, $ouuid);

        return $this->redirectToRoute('ems_data_trash', [
            'contentType' => $contentType->getId(),
        ]);
    }


    /**
     * @Route("/data/draft/{contentTypeId}", name="data.draft_in_progress"))
     */
    public function draftInProgressAction($contentTypeId, Request $request)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var ContentTypeRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:ContentType');


        $contentType = $repository->find($contentTypeId);


        if (!$contentType) {
            throw new NotFoundHttpException('Content type not found');
        }

        /** @var RevisionRepository $revisionRep */
        $revisionRep = $em->getRepository('EMSCoreBundle:Revision');

        $revisions = $revisionRep->findInProgresByContentType($contentType, $this->getUserService()->getCurrentUser()->getCircles(), $this->get('security.authorization_checker')->isGranted('ROLE_ADMIN'));


        return $this->render('@EMSCore/data/draft-in-progress.html.twig', [
            'contentType' => $contentType,
            'revisions' => $revisions
        ]);
    }

    /**
     * @Route("/data/view/{environmentName}/{type}/{ouuid}", name="data.view")
     */
    public function viewDataAction($environmentName, $type, $ouuid, Request $request)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var EnvironmentRepository $environmentRepo */
        $environmentRepo = $em->getRepository('EMSCoreBundle:Environment');
        $environments = $environmentRepo->findBy([
            'name' => $environmentName,
        ]);
        if (!$environments || count($environments) != 1) {
            throw new NotFoundHttpException('Environment not found');
        }

        /** @var ContentTypeRepository $contentTypeRepo */
        $contentTypeRepo = $em->getRepository('EMSCoreBundle:ContentType');
        $contentTypes = $contentTypeRepo->findBy([
            'name' => $type,
            'deleted' => false,
        ]);

        $contentType = null;
        if ($contentTypes && count($contentTypes) == 1) {
            $contentType = $contentTypes[0];
        }

        try {
            /** @var Client $client */
            $client = $this->getElasticsearch();
            $result = $client->get([
                'index' => $this->getContentTypeService()->getIndex($contentType, $environments[0]),
                'type' => $type,
                'id' => $ouuid,
            ]);
        } catch (Missing404Exception $e) {
            throw new NotFoundHttpException($type . ' not found');
        }

        return $this->render('@EMSCore/data/view-data.html.twig', [
            'object' => $result,
            'environment' => $environments[0],
            'contentType' => $contentType,
        ]);
    }

    /**
     * @Route("/data/revisions-in-environment/{environment}/{type}:{ouuid}", name="data.revision_in_environment", defaults={"deleted":0})
     * @ParamConverter("contentType", options={"mapping": {"type" = "name", "deleted" = "deleted"}})
     * @ParamConverter("environment", options={"mapping": {"environment" = "name"}})
     */
    public function revisionInEnvironmentDataAction(ContentType $contentType, $ouuid, Environment $environment, Request $request)
    {
        $revision = $this->getDataService()->getRevisionByEnvironment($ouuid, $contentType, $environment);
        if (!$revision) {
            $this->addFlash('warning', 'No revision found in ' . $environment->getName() . ' for ' . $contentType->getName() . ':' . $ouuid);
            return $this->redirectToRoute('data.draft_in_progress', ['contentTypeId' => $contentType->getId()]);
        }
        return $this->redirectToRoute('data.revisions', [
            'type' => $contentType->getName(),
            'ouuid' => $ouuid,
            'revisionId' => $revision->getId(),
        ]);
    }

    /**
     * @Route("/public-key" , name="ems_get_public_key")
     */
    public function publicKey(Request $request)
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/plain');
        $response->setContent($this->getDataService()->getPublicKey());
        return $response;
    }

    /**
     * @Route("/data/revisions/{type}:{ouuid}/{revisionId}/{compareId}", defaults={"revisionId": false, "compareId": false} , name="data.revisions")
     * @Route("/data/revisions/{type}:{ouuid}/{revisionId}/{compareId}", defaults={"revisionId": false, "compareId": false} , name="ems_content_revisions_view")
     */
    public function revisionsDataAction($type, $ouuid, $revisionId, $compareId, Request $request, DataService $dataService)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var ContentTypeRepository $contentTypeRepo */
        $contentTypeRepo = $em->getRepository('EMSCoreBundle:ContentType');

        $contentTypes = $contentTypeRepo->findBy([
            'deleted' => false,
            'name' => $type,
        ]);
        if (!$contentTypes || count($contentTypes) != 1) {
            throw new NotFoundHttpException('Content Type not found');
        }
        /** @var ContentType $contentType */
        $contentType = $contentTypes[0];

        if (!$contentType->getEnvironment()->getManaged()) {
            return $this->redirectToRoute('data.view', [
                'environmentName' => $contentType->getEnvironment()->getName(),
                'type' => $type,
                'ouuid' => $ouuid
            ]);
        }


        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');

        /**@var Revision $revision */
        if (!$revisionId) {
            $revision = $repository->findOneBy([
                'endTime' => null,
                'ouuid' => $ouuid,
                'deleted' => false,
                'contentType' => $contentType,
            ]);
        } else {
            $revision = $repository->findOneById($revisionId);
        }

        $compareData = false;
        if($compareId) {

            $this->addFlash('warning', 'The compare is a beta functionality');
            /**@var Revision $compareRevision*/
            $compareRevision = $repository->findOneById($compareId);
            if($compareRevision) {

                $compareData = $compareRevision->getRawData();
                if($revision->getContentType() === $compareRevision->getContentType() && $revision->getOuuid() == $compareRevision->getOuuid()) {
                    if($compareRevision->getCreated() <= $revision->getCreated()  ){
                        $this->addFlash('notice', 'Compared with the revision of '.$compareRevision->getCreated()->format($this->getParameter('ems_core.date_time_format')));
                    }
                    else{
                        $this->addFlash('warning', 'Compared with the revision of '.$compareRevision->getCreated()->format($this->getParameter('ems_core.date_time_format')). ' wich one is more recent.');
                    }
                }
                else {
                    $this->addFlash('notice', 'Compared with '.$compareRevision->getContentType().':'.$compareRevision->getOuuid().' of '.$compareRevision->getCreated()->format($this->getParameter('ems_core.date_time_format')));
                }
            }
            else {
                $this->addFlash('warning', 'Revision to compare with not found');
            }
        }


        if (!$revision || $revision->getOuuid() != $ouuid || $revision->getContentType() != $contentType || $revision->getDeleted()) {
            throw new NotFoundHttpException('Revision not found');
        }

        $dataService->testIntegrityInIndexes($revision);


        $this->loadAutoSavedVersion($revision);

// 		$this->getDataService()->loadDataStructure($revision);

// 		$revision->getDataField()->orderChildren();


        $page = $request->query->get('page', 1);

        $revisionsSummary = $repository->getAllRevisionsSummary($ouuid, $contentTypes[0], $page);
        $lastPage = $repository->revisionsLastPage($ouuid, $contentTypes[0]);
        $counter = $repository->countRevisions($ouuid, $contentTypes[0]);
        $firstElemOfPage = $repository->firstElemOfPage($page);

        $availableEnv = $em->getRepository('EMSCoreBundle:Environment')->findAvailableEnvironements(
            $revision->getContentType()->getEnvironment());


        $form = $this->createForm(RevisionType::class, $revision, ['raw_data' => $revision->getRawData()]);


        $objectArray = $form->getData()->getRawData();

        $dataFields = $this->getDataService()->getDataFieldsStructure($form->get('data'));


        /** @var Client $client */
        $client = $this->getElasticsearch();


        $searchForm = new Search();
        $searchForm->setContentTypes($this->getContentTypeService()->getAllNames());
        $searchForm->setEnvironments($this->getContentTypeService()->getAllDefaultEnvironmentNames());
        $searchForm->setSortBy('_uid');
        $searchForm->setSortOrder('asc');

        $filter = $searchForm->getFilters()[0];
        $filter->setBooleanClause('must');
        $filter->setField($revision->getContentType()->getRefererFieldName());
        $filter->setPattern($type . ':' . $ouuid);
        if (empty($revision->getContentType()->getRefererFieldName())) {
            $filter->setOperator('match_and');
        } else {
            $filter->setOperator('term');
        }

// 		/**@var Form $form*/
// 		$form = $this->createForm ( SearchFormType::class, $searchForm, [
// 		    'method' => 'GET',
// 		] );

        $refParams = [
            '_source' => false,
            'type' => $searchForm->getContentTypes(),
            'index' => $this->getContentTypeService()->getAllAliases(),
            'size' => 100,
            'body' => $this->getSearchService()->generateSearchBody($searchForm),
        ];


        return $this->render('@EMSCore/data/revisions-data.html.twig', [
            'revision' => $revision,
            'revisionsSummary' => $revisionsSummary,
            'availableEnv' => $availableEnv,
            'object' => $revision->getObject($objectArray),
            'referrers' => $client->search($refParams),
            'referrersFilter' => $filter,
            'page' => $page,
            'lastPage' => $lastPage,
            'counter' => $counter,
            'firstElemOfPage' => $firstElemOfPage,
            'dataFields' => $dataFields,
            'compareData' => $compareData,
            'compareId' => $compareId,
        ]);
    }

    public function getNewestRevision($type, $ouuid)
    {
        return $this->getDataService()->getNewestRevision($type, $ouuid);
    }

    /**
     *
     * @param unknown $type
     * @param unknown $ouuid
     * @param unknown $fromRev
     * @return Revision
     */
    public function initNewDraft($type, $ouuid, $fromRev = null)
    {
        return $this->getDataService()->initNewDraft($type, $ouuid, $fromRev);
    }


    /**
     *
     * @Route("/data/copy/{environment}/{type}/{ouuid}", name="revision.copy"))
     * @Method({"GET"})
     */
    public function copyAction($environment, $type, $ouuid, Request $request)
    {
        $envObj = $this->getEnvironmentService()->getByName($environment);
        $dataRaw = $this->getElasticsearch()->get([
            'index' => $envObj->getAlias(),
            'id' => $ouuid,
            'type' => $type,
        ]);

        $this->addFlash('notice', 'The data of this object has been copied');
        $request->getSession()->set('ems_clipboard', $dataRaw['_source']);

        return $this->redirectToRoute('data.view', [
            'environmentName' => $environment,
            'type' => $type,
            'ouuid' => $ouuid,
        ]);
    }


    /**
     *
     * @Route("/data/new-draft/{type}/{ouuid}", name="revision.new-draft"))
     * @Method({"POST"})
     */
    public function newDraftAction($type, $ouuid, Request $request)
    {
        return $this->redirectToRoute('revision.edit', [
            'revisionId' => $this->initNewDraft($type, $ouuid)->getId()
        ]);
    }


    /**
     *
     * @Route("/data/delete/{type}/{ouuid}", name="object.delete"))
     * @Method({"POST"})
     */
    public function deleteAction($type, $ouuid, Request $request)
    {
        $revision = $this->getDataService()->getNewestRevision($type, $ouuid);
        $contentType = $revision->getContentType();
        $found = false;
        foreach ($this->getEnvironmentService()->getAll() as $environment) {
            /**@var Environment $environment */
            if ($environment !== $revision->getContentType()->getEnvironment()) {
                try {
                    $sibling = $this->getDataService()->getRevisionByEnvironment($ouuid, $revision->getContentType(), $environment);
                    if ($sibling) {
                        $this->addFlash('warning', 'A revision as been found in ' . $environment->getName() . '. Consider to unpublish it first.');
                        $found = true;
                    }
                } catch (NoResultException $e) {

                }
            }
        }

        if ($found) {
            return $this->redirectToRoute('data.revisions', [
                'type' => $type,
                'ouuid' => $ouuid,
            ]);
        }

        $this->getDataService()->delete($type, $ouuid);

        return $this->redirectToRoute('ems_data_default_search', [
            'name' => $contentType->getName(),
        ]);
    }

    public function discardDraft(Revision $revision)
    {
        return $this->getDataService()->discardDraft($revision);
    }

    /**
     *
     * @Route("/data/draft/discard/{revisionId}", name="revision.discard"))
     * @Method({"POST"})
     */
    public function discardRevisionAction($revisionId, Request $request)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Revision $revision */
        $revision = $repository->find($revisionId);

        if (!$revision) {
            throw $this->createNotFoundException('Revision not found');
        }
        if (!$revision->getDraft() || null != $revision->getEndTime()) {
            throw BadRequestHttpException('Only authorized on a draft');
        }


        $contentTypeId = $revision->getContentType()->getId();
        $type = $revision->getContentType()->getName();
        $autoPublish = $revision->getContentType()->isAutoPublish();
        $ouuid = $revision->getOuuid();

        $hasPreviousRevision = $this->discardDraft($revision);

        if (null != $ouuid && $hasPreviousRevision) {

            if($autoPublish) {
                return $this->reindexRevisionAction($hasPreviousRevision, $request, true);
            }

            return $this->redirectToRoute('data.revisions', [
                'type' => $type,
                'ouuid' => $ouuid,
            ]);
        }
        return $this->redirectToRoute('data.draft_in_progress', [
            'contentTypeId' => $contentTypeId
        ]);
    }


    /**
     *
     * @Route("/data/cancel/{revision}", name="revision.cancel"))
     * @Method({"POST"})
     */
    public function cancelModificationsAction(Revision $revision, Request $request)
    {
        $contentTypeId = $revision->getContentType()->getId();
        $type = $revision->getContentType()->getName();
        $ouuid = $revision->getOuuid();


        $this->lockRevision($revision);

        $em = $this->getDoctrine()->getManager();
        $revision->setAutoSave(null);
        $em->persist($revision);
        $em->flush();

        if (null != $ouuid) {

            if($revision->getContentType()->isAutoPublish()) {
                $this->addFlash('warning', 'Elasticms was not able to determine if this draft can be silently published');
            }

            return $this->redirectToRoute('data.revisions', [
                'type' => $type,
                'ouuid' => $ouuid,
            ]);
        }
        return $this->redirectToRoute('data.draft_in_progress', [
            'contentTypeId' => $contentTypeId
        ]);
    }


    /**
     * @Route("/data/revision/re-index/{revisionId}", name="revision.reindex"))
     * @Method({"POST"})
     */
    public function reindexRevisionAction($revisionId, Request $request, $defaultOnly=false)
    {

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Revision $revision */
        $revision = $repository->find($revisionId);

        if (!$revision) {
            throw $this->createNotFoundException('Revision not found');
        }

        $this->lockRevision($revision);

        /** @var Client $client */
        $client = $this->getElasticsearch();


        try {
            $this->getDataService()->reloadData($revision);

            $objectArray = $this->getDataService()->sign($revision);


            $objectArray[CoreBundle\Service\Mapping::PUBLISHED_DATETIME_FIELD] = (new \DateTime())->format(\DateTime::ISO8601);

            /** @var \EMS\CoreBundle\Entity\Environment $environment */
            foreach ($revision->getEnvironments() as $environment) {
                if(!$defaultOnly || $environment === $revision->getContentType()->getEnvironment()) {
                    $index = $this->getContentTypeService()->getIndex($revision->getContentType(), $environment);

                    $status = $client->index([
                        'id' => $revision->getOuuid(),
                        'index' => $index,
                        'type' => $revision->getContentType()->getName(),
                        'body' => $objectArray
                    ]);

                    $this->addFlash('notice', 'Reindexed in ' . $environment->getName());
                }
            }
            $em->persist($revision);
            $em->flush();
        } catch (\Exception $e) {
            $this->addFlash('warning', 'Reindexing has failed: ' . $e->getMessage());
        }
        return $this->redirectToRoute('data.revisions', [
            'ouuid' => $revision->getOuuid(),
            'type' => $revision->getContentType()->getName(),
            'revisionId' => $revision->getId(),
        ]);

    }

    /**
     * @Route("/public/view/{viewId}", name="ems_custom_view_public", defaults={"public": true})
     * @Route("/data/custom-index-view/{viewId}", name="data.customindexview", defaults={"public": false})
     * @Route("/data/custom-index-view/{viewId}", name="ems_custom_view_protected", defaults={"public": false})
     */
    public function customIndexViewAction($viewId, $public, Request $request)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var ViewRepository $viewRepository */
        $viewRepository = $em->getRepository('EMSCoreBundle:View');

        $view = $viewRepository->find($viewId);
        /** @var View $view * */

        if (!$view || ($public && !$view->isPublic()) ) {
            throw new NotFoundHttpException('View type not found');
        }

        /** @var ViewType $viewType */
        $viewType = $this->get($view->getType());

        return $viewType->generateResponse($view, $request);
    }

    /**
     * @Route("/public/template/{environmentName}/{templateId}/{ouuid}/{_download}", defaults={"_download": false, "public": true} , name="ems_data_custom_template_public"))
     * @Route("/data/custom-view/{environmentName}/{templateId}/{ouuid}/{_download}", defaults={"_download": false, "public": false} , name="data.customview"))
     * @Route("/data/template/{environmentName}/{templateId}/{ouuid}/{_download}", defaults={"_download": false, "public": false} , name="ems_data_custom_template_protected"))
     */
    public function customViewAction($environmentName, $templateId, $ouuid, Request $request, $_download, $public)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var TemplateRepository $templateRepository */
        $templateRepository = $em->getRepository('EMSCoreBundle:Template');

        /** @var Template $template * */
        $template = $templateRepository->find($templateId);

        if (!$template || ($template && !$template->isPublic())) {
            throw new NotFoundHttpException('Template type not found');
        }

        /** @var EnvironmentRepository $environmentRepository */
        $environmentRepository = $em->getRepository('EMSCoreBundle:Environment');

        /** @var Environment $environment * */
        $environment = $environmentRepository->findBy([
            'name' => $environmentName,
        ]);

        if (!$environment || count($environment) != 1) {
            throw new NotFoundHttpException('Environment type not found');
        }

        $environment = $environment[0];

        /** @var Client $client */
        $client = $this->getElasticsearch();

        $object = $client->get([
            'index' => $environment->getAlias(),
            'type' => $template->getContentType()->getName(),
            'id' => $ouuid
        ]);

        $twig = $this->getTwig();

        try {
            //TODO why is the body generated and passed to the twig file while the twig file does not use it?
            //Asked by dame
            //If there is an error in the twig the user will get an 500 error page, this solution is not perfect but at least the template is tested
            $body = $twig->createTemplate($template->getBody());
        } catch (\Twig_Error $e) {
            $this->addFlash('error', 'There is something wrong with the template body field ' . $template->getName());
            $body = $twig->createTemplate('error in the template!');
        }

        if($template->getRenderOption() === RenderOptionType::PDF && ($_download || !$template->getPreview()) ) {
            $output = $body->render([
                'environment' => $environment,
                'contentType' => $template->getContentType(),
                'object' => $object,
                'source' => $object['_source'],
                '_download' => ($_download || !$template->getPreview()),
            ]);

            // instantiate and use the dompdf class
            $dompdf = new Dompdf();
            $dompdf->loadHtml($output);

            // (Optional) Setup the paper size and orientation
            $dompdf->setPaper($template->getSize()?$template->getSize():'A3', $template->getOrientation()?$template->getOrientation():'portrait');

            // Render the HTML as PDF
            $dompdf->render();

            // Output the generated PDF to Browser
            $dompdf->stream();
            exit;
        }
        if ($_download || (strcmp($template->getRenderOption(), RenderOptionType::EXPORT) === 0 && !$template->getPreview())) {
            if (null != $template->getMimeType()) {
                header('Content-Type: ' . $template->getMimeType());
            }

            $filename = $ouuid;
            if (null != $template->getFilename()) {
                try {
                    $filename = $twig->createTemplate($template->getFilename());
                } catch (\Twig_Error $e) {
                    $this->addFlash('error', 'There is something wrong with the template filename field ' . $template->getName());
                    $filename = $twig->createTemplate('error in the template!');
                }

                $filename = $filename->render([
                    'environment' => $environment,
                    'contentType' => $template->getContentType(),
                    'object' => $object,
                    'source' => $object['_source'],
                ]);
                $filename = preg_replace('~[\r\n]+~', '', $filename);
            }

            if (null != $template->getExtension()) {
                header("Content-Disposition: attachment; filename=" . $filename . '.' . $template->getExtension());
            }

            $output = $body->render([
                'environment' => $environment,
                'contentType' => $template->getContentType(),
                'object' => $object,
                'source' => $object['_source'],
            ]);
            echo $output;

            exit;
        }

        return $this->render('@EMSCore/data/custom-view.html.twig', [
            'template' => $template,
            'object' => $object,
            'environment' => $environment,
            'contentType' => $template->getContentType(),
            'body' => $body
        ]);

    }

    /**
     * @Route("/data/custom-view-job/{environmentName}/{templateId}/{ouuid}", name="ems_job_custom_view")
     * @method ({"POST"})
     */
    public function customViewJobAction($environmentName, $templateId, $ouuid)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var CoreBundle\Entity\Template $template * */
        $template = $em->getRepository(Template::class)->find($templateId);
        /** @var CoreBundle\Entity\Environment $env */
        $env = $em->getRepository(Environment::class)->findOneByName($environmentName);

        if(!$template || !$env) {
            throw new NotFoundHttpException();
        }

        $object = $this->getElasticsearch()->get([
            'index' => $env->getAlias(),
            'type' => $template->getContentType()->getName(),
            'id' => $ouuid,
        ]);

        $success = false;
        try {
            $command = $this->getTwig()->createTemplate($template->getBody())->render([
                'environment' => $env->getName(),
                'contentType' => $template->getContentType(),
                'object' => $object,
                'source' => $object['_source'],
            ]);

            /** @var CoreBundle\Service\JobService $jobService */
            $jobService = $this->get('ems.service.job');
            $job = $jobService->createCommand($this->getUser(), $command);

            $jobService->run($job);

            $success = true;
            $this->addFlash('notice', sprintf('The job "%s" was successfully started', $template->getName()));
        } catch (\Exception $e) {
            $this->getLogger()->error($e->getMessage());
            $this->addFlash('error', sprintf('Something went wrong and the job "%s" was not successfully started', $template->getName()));
        }

        return $this->returnJson($success);
    }

    private function loadAutoSavedVersion(Revision $revision)
    {
        if (null != $revision->getAutoSave()) {
            $revision->setRawData($revision->getAutoSave());
            $this->addFlash('warning', "Data were loaded from an autosave version by " . $revision->getAutoSaveBy() . " at " . $revision->getAutoSaveAt()->format($this->getParameter('ems_core.date_time_format')));
        }
    }


    /**
     * @Route("/data/revision/{revisionId}.json", name="revision.ajaxupdate"), defaults={"_format": "json"})
     * @Method({"POST"})
     */
    public function ajaxUpdateAction($revisionId, Request $request)
    {

        $em = $this->getDoctrine()->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Revision $revision */
        $revision = $repository->find($revisionId);

        if (!$revision) {
            throw new NotFoundHttpException('Revision not found');
        }

        if (!$revision->getDraft() || $revision->getEndTime() !== null) {
            $this->addFlash("warning", "The autosave didn't worked as this revision (" . $revision->getContentType()->getSingularName() . ($revision->getOuuid() ? ":" . $revision->getOuuid() : "") . ") has been already finalized .");
            $response = $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => false,
            ]);
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        if (empty($request->request->get('revision')) || empty($request->request->get('revision')['allFieldsAreThere']) || !$request->request->get('revision')['allFieldsAreThere']) {
            $this->addFlash('error', 'Incomplete request, some fields of the request are missing, please verifiy your server configuration. (i.e.: max_input_vars in php.ini)');
            $this->addFlash('error', 'Your modification are not saved!');
        } else {

            $this->lockRevision($revision);
            $this->getLogger()->addDebug('Revision locked');

            $backup = $revision->getRawData();
            $form = $this->createForm(RevisionType::class, $revision, ['raw_data' => $revision->getRawData()]);
            //If the bag is not empty the user already see its content when opening the edit page
            $request->getSession()->getBag('flashes')->clear();


            /**little trick to reorder collection*/
            $requestRevision = $request->request->get('revision');
            $this->reorderCollection($requestRevision);
            $request->request->set('revision', $requestRevision);
            /**end little trick to reorder collection*/

            $form->handleRequest($request);
            $revision->setAutoSave($revision->getRawData());
            $revision->setRawData($backup);


            $revision->setAutoSaveAt(new \DateTime());
            $revision->setAutoSaveBy($this->getUser()->getUsername());

            $em->persist($revision);
            $em->flush();

            $this->getDataService()->isValid($form);
            $formErrors = $form->getErrors(true, true);

            if($formErrors->count() === 0 && $revision->getContentType()->isAutoPublish()) {
                $this->getPublishService()->silentPublish($revision);
            }
        }

        $response = $this->render('@EMSCore/data/ajax-revision.json.twig', [
            'success' => true,
            'formErrors' => $formErrors,
        ]);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }


    /**
     * @Route("/data/draft/finalize/{revision}", name="revision.finalize"))
     */
    public function finalizeDraftAction(Revision $revision)
    {


        $this->getDataService()->loadDataStructure($revision);
        try {
            $form = $this->createForm(RevisionType::class, $revision, ['raw_data' => $revision->getRawData()]);
            if (!empty($revision->getAutoSave())) {
                $this->addFlash("error", "This draft (" . $revision->getContentType()->getSingularName() . ($revision->getOuuid() ? ":" . $revision->getOuuid() : "") . ") can't be finalized, as an autosave is pending.");
                return $this->render('@EMSCore/data/edit-revision.html.twig', [
                    'revision' => $revision,
                    'form' => $form->createView(),
                    'stylesSets' => $this->getWysiwygStylesSetService()->getStylesSets(),
                ]);
            }

            $revision = $this->getDataService()->finalizeDraft($revision, $form);
            if (count($form->getErrors()) !== 0) {
                $this->addFlash("error", "This draft (" . $revision->getContentType()->getSingularName() . ($revision->getOuuid() ? ":" . $revision->getOuuid() : "") . ") can't be finalized.");
                return $this->render('@EMSCore/data/edit-revision.html.twig', [
                    'revision' => $revision,
                    'form' => $form->createView(),
                    'stylesSets' => $this->getWysiwygStylesSetService()->getStylesSets(),
                ]);
            }

        } catch (\Exception $e) {
            $this->addFlash("error", "This draft (" . $revision->getContentType()->getSingularName() . ($revision->getOuuid() ? ":" . $revision->getOuuid() : "") . ") can't be finalized.");
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('revision.edit', [
                'revisionId' => $revision->getId(),
            ]);
        }

        return $this->redirectToRoute('data.revisions', [
            'ouuid' => $revision->getOuuid(),
            'type' => $revision->getContentType()->getName(),
            'revisionId' => $revision->getId(),
        ]);
    }

    public function finalizeDraft(Revision $revision, \Symfony\Component\Form\Form $form = null, $username = null)
    {
//		TODO: User validators
// 		$validator = $this->get('validator');
// 		$errors = $validator->validate($revision);

        return $this->getDataService()->finalizeDraft($revision, $form, $username);
    }

    private function reorderCollection(&$input)
    {
        if (is_array($input) && !empty($input)) {
            $keys = array_keys($input);
            if (is_int($keys[0])) {
                sort($keys);
                $temp = [];
                $loop0 = 0;
                foreach ($input as $item)
                {
                    $temp[$keys[$loop0]] = $item;
                    ++$loop0;
                }
                $input = $temp;
            }
            foreach ($input as &$elem) {
                $this->reorderCollection($elem);
            }
        }
    }

    /**
     * @Route("/data/draft/edit/{revisionId}", name="ems_revision_edit"))
     * @Route("/data/draft/edit/{revisionId}", name="revision.edit"))
     */
    public function editRevisionAction($revisionId, Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Revision $revision */
        $revision = $repository->find($revisionId);

        if (!$revision) {
            throw new NotFoundHttpException('Unknown revision');
        }

        $this->lockRevision($revision);
        $this->getLogger()->debug('Revision ' . $revisionId . ' locked');

        //TODO:Only a super user can edit a archived revision

        if ($request->isMethod('GET')) {
            $this->loadAutoSavedVersion($revision);
        }


// 		$this->getDataService()->loadDataStructure($revision);
// 		$this->getDataService()->generateInputValues($revision->getDataField());
// 		$this->getLogger()->debug('DataField structure generated');


        $form = $this->createForm(RevisionType::class, $revision, [
            'has_clipboard' => $request->getSession()->has('ems_clipboard'),
            'has_copy' => $this->getAuthorizationChecker()->isGranted('ROLE_COPY_PASTE'),
            'raw_data' => $revision->getRawData(),
        ]);

        $this->getLogger()->debug('Revision\'s form created');


        /**little trick to reorder collection*/
        $requestRevision = $request->request->get('revision');
        $this->reorderCollection($requestRevision);
        $request->request->set('revision', $requestRevision);
        /**end little trick to reorder collection*/


        $form->handleRequest($request);

        $this->getLogger()->debug('Revision request form handled');


        if ($form->isSubmitted()) {//Save, Finalize or Discard

            if (empty($request->request->get('revision')) || empty($request->request->get('revision')['allFieldsAreThere']) || !$request->request->get('revision')['allFieldsAreThere']) {
                $this->addFlash('error', 'Incomplete request, some fields of the request are missing, please verifiy your server configuration. (i.e.: max_input_vars in php.ini)');
                return $this->redirectToRoute('data.revisions', [
                    'ouuid' => $revision->getOuuid(),
                    'type' => $revision->getContentType()->getName(),
                    'revisionId' => $revision->getId(),
                ]);
            }


            $revision->setAutoSave(null);
            if (!array_key_exists('discard', $request->request->get('revision'))) {//Save, Copy, Paste or Finalize

                //Save anyway
                /** @var Revision $revision */
                $revision = $form->getData();

                $this->getLogger()->debug('Revision extracted from the form');


                $objectArray = $revision->getRawData();

                if (array_key_exists('paste', $request->request->get('revision'))) {//Paste
                    $this->addFlash('notice', 'Data have been paste');
                    $objectArray = array_merge($objectArray, $request->getSession()->get('ems_clipboard', []));
                    $this->getLogger()->debug('Paste data have been merged');
                }

                if (array_key_exists('copy', $request->request->get('revision'))) {//Copy
                    $request->getSession()->set('ems_clipboard', $objectArray);
                    $this->addFlash('notice', 'Data have been copied');
                }

                $revision->setRawData($objectArray);


                $this->getDataService()->setMetaFields($revision);

                $this->getLogger()->debug('Revision before persist');
                $em->persist($revision);
                $em->flush();

                $this->getLogger()->debug('Revision after persist flush');

                if (array_key_exists('publish', $request->request->get('revision'))) {//Finalize
// 					try{
                    $revision = $this->finalizeDraft($revision, $form);
                    if (count($form->getErrors()) === 0) {
                        return $this->redirectToRoute('data.revisions', [
                            'ouuid' => $revision->getOuuid(),
                            'type' => $revision->getContentType()->getName(),
                        ]);
                    } else {
                        //$this->addFlash("warning", "This draft (".$revision->getContentType()->getSingularName().($revision->getOuuid()?":".$revision->getOuuid():"").") can't be finalized.");
                        return $this->render('@EMSCore/data/edit-revision.html.twig', [
                            'revision' => $revision,
                            'form' => $form->createView(),
                            'stylesSets' => $this->getWysiwygStylesSetService()->getStylesSets(),
                        ]);
                    }
// 					}
// 					catch (\Exception $e){
// 						$this->addFlash('error', 'The draft has been saved but something when wrong when we tried to publish it. '.$revision->getContentType()->getName().':'.$revision->getOuuid());
// 						$this->addFlash('error', $e->getMessage());
// 						return $this->redirectToRoute('revision.edit', [
// 								'revisionId' => $revisionId,
// 						]);
// 					}

                }
            }

            //if paste or copy
            if (array_key_exists('paste', $request->request->get('revision')) || array_key_exists('copy', $request->request->get('revision'))) {//Paste or copy
                return $this->redirectToRoute('revision.edit', [
                    'revisionId' => $revisionId,
                ]);
            }
            //if Save or Discard
            if (null != $revision->getOuuid()) {

                if(count($form->getErrors()) === 0 && $revision->getContentType()->isAutoPublish()) {
                    $this->getPublishService()->silentPublish($revision);
                }

                return $this->redirectToRoute('data.revisions', [
                    'ouuid' => $revision->getOuuid(),
                    'type' => $revision->getContentType()->getName(),
                    'revisionId' => $revision->getId(),
                ]);
            } else {
                return $this->redirectToRoute('data.draft_in_progress', [
                    'contentTypeId' => $revision->getContentType()->getId(),
                ]);
            }

        } else {
            $isValid = $this->getDataService()->isValid($form);
            if (!$isValid) {
                $this->addFlash("warning", "This draft (" . $revision->getContentType()->getSingularName() . ($revision->getOuuid() ? ":" . $revision->getOuuid() : "") . ") can't be finalized.");
            }
        }

        // Call Audit service for log
        $this->get("ems.service.audit")->auditLog('DataController:editRevision', $revision->getRawData());
        $this->getLogger()->debug('Start twig rendering');
        return $this->render('@EMSCore/data/edit-revision.html.twig', [
            'revision' => $revision,
            'form' => $form->createView(),
            'stylesSets' => $this->getWysiwygStylesSetService()->getStylesSets(),
        ]);
    }

    /**
     * deprecated should removed
     *
     * @param Revision $revision
     * @param string $publishEnv
     * @param string $super
     */
    private function lockRevision(Revision $revision, $publishEnv = false, $super = false)
    {
        $this->getDataService()->lockRevision($revision, $publishEnv, $super);
    }


    /**
     * @throws HasNotCircleException
     * @Route("/data/add/{contentType}", name="data.add"))
     */
    public function addAction(ContentType $contentType, Request $request)
    {
        $userCircles = $this->getUser()->getCircles();
        $environment = $contentType->getEnvironment();
        $environmentCircles = $environment->getCircles();
        if (!empty($environmentCircles)) {
            if (empty($userCircles)) {
                throw new HasNotCircleException($environment);
            }
            $found = false;
            foreach ($userCircles as $userCircle) {
                if (in_array($userCircle, $environmentCircles)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new HasNotCircleException($environment);
            }
        }

        $em = $this->getDoctrine()->getManager();

        $revision = new Revision();

        if(! empty($contentType->getDefaultValue())){

            $twig = $this->getTwig();
            try {
                $template = $twig->createTemplate($contentType->getDefaultValue());
                $defaultValue = $template->render([
                    'environment' => $environment,
                    'contentType' => $contentType,
                ]);
                $raw = json_decode($defaultValue, true);
                if($raw === NULL) {
                    $this->addFlash('error', 'elasticms was not able to initiate the default value (json_decode), please check the content type\'s configuration');
                }
                else {
                    $revision->setRawData($raw);
                }
            } catch (\Twig_Error $e) {
                $this->addFlash('error', 'elasticms was not able to initiate the default value (twig error), please check the content type\'s configuration');
            }
            
        }

        $form = $this->createFormBuilder($revision)
            ->add('ouuid', IconTextType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Auto-generated if left empty'
                ],
                'required' => false
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Create ' . $contentType->getName() . ' draft',
                'attr' => [
                    'class' => 'btn btn-primary pull-right'
                ]
            ])
            ->getForm();

        $form->handleRequest($request);


        if (($form->isSubmitted() && $form->isValid()) || !$contentType->getAskForOuuid()) {
            /** @var Revision $revision */
            $revision = $form->getData();

            if (!$this->get('security.authorization_checker')->isGranted($contentType->getCreateRole())) {
                throw new PrivilegeException($revision);
            }


            if (null != $revision->getOuuid()) {
                $revisionRepository = $em->getRepository('EMSCoreBundle:Revision');
                $anotherObject = $revisionRepository->findBy([
                    'contentType' => $contentType,
                    'ouuid' => $revision->getOuuid(),
                    'endTime' => null
                ]);

                if (count($anotherObject) != 0) {
                    $form->get('ouuid')->addError(new FormError('Another ' . $contentType->getName() . ' with this identifier already exists'));
// 					$form->addError(new FormError('Another '.$contentType->getName().' with this identifier already exists'));
                }
            }

            if (($form->isSubmitted() && $form->isValid()) || !$contentType->getAskForOuuid()) {
                $now = new \DateTime('now');
                $revision->setContentType($contentType);
                $revision->setDraft(true);
                $revision->setDeleted(false);
                $revision->setStartTime($now);
                $revision->setEndTime(null);
                $revision->setLockBy($this->getUser()->getUsername());
                $revision->setLockUntil(new \DateTime($this->getParameter('ems_core.lock_time')));

                if ($contentType->getCirclesField()) {
                    $fieldType = $contentType->getFieldType()->getChildByPath($contentType->getCirclesField());
                    if ($fieldType) {
                        /**@var \EMS\CoreBundle\Entity\User $user */
                        $user = $this->getUser();
                        $options = $fieldType->getDisplayOptions();
                        if (isset($options['multiple']) && $options['multiple']) {
                            //merge all my circles with the default value
                            $circles = [];
                            if (isset($options['defaultValue'])) {
                                $circles = json_decode($options['defaultValue']);
                                if (!is_array($circles)) {
                                    $circles = [$circles];
                                }
                            }
                            $circles = array_merge($circles, $user->getCircles());
                            $revision->setRawData([$contentType->getCirclesField() => $circles]);
                            $revision->setCircles($circles);

                        } else {
                            //set first of my circles
                            if (!empty($user->getCircles())) {
                                $revision->setRawData([$contentType->getCirclesField() => $user->getCircles()[0]]);
                                $revision->setCircles([$user->getCircles()[0]]);
                            }
                        }
                    }
                }
                $this->getDataService()->setMetaFields($revision);

                $em->persist($revision);
                $em->flush();

                return $this->redirectToRoute('revision.edit', [
                    'revisionId' => $revision->getId()
                ]);
            }
        }

        return $this->render('@EMSCore/data/add.html.twig', [
            'contentType' => $contentType,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/data/revisions/revert/{id}", name="revision.revert"))
     * @Method({"POST"})
     */
    public function revertRevisionAction(Revision $revision, Request $request)
    {
        $type = $revision->getContentType()->getName();
        $ouuid = $revision->getOuuid();

        $newestRevision = $this->getNewestRevision($type, $ouuid);
        if ($newestRevision->getDraft()) {
            //TODO: ????
        }

        $revertedRevsision = $this->initNewDraft($type, $ouuid, $revision);
        $this->addFlash('notice', 'Revision ' . $revision->getId() . ' reverted as draft');

        return $this->redirectToRoute('revision.edit', [
            'revisionId' => $revertedRevsision->getId()
        ]);
    }

    /**
     * @Route("/data/link/{key}", name="data.link")
     */
    public function linkDataAction($key, Request $request)
    {
        $splitted = explode(':', $key);
        if ($splitted && count($splitted) == 3) {
            $category = $splitted[0]; // object or asset
            $type = $splitted[1];
            $ouuid = $splitted[2];
        }

        if (null != $ouuid && null != $type) {
            /** @var EntityManager $em */
            $em = $this->getDoctrine()->getManager();

            /** @var RevisionRepository $repository */
            $repository = $em->getRepository('EMSCoreBundle:Revision');

            /**@var ContentTypeService $ctService */
            $ctService = $this->getContentTypeService();


            $contentType = $ctService->getByName($type);

            if (empty($contentType)) {
                throw new NotFoundHttpException('Content type ' . $type . 'not found');
            }

            /**@var Revision $revision */
            $revision = $repository->findByOuuidAndContentTypeAndEnvironnement($contentType, $ouuid, $contentType->getEnvironment());

            if (!$revision) {
                throw new NotFoundHttpException('Impossible to find this item : ' . $ouuid);
            }


            // For each type, we must perform a different redirect.
            if ($category == 'object') {
                return $this->redirectToRoute('data.revisions', [
                    'type' => $type,
                    'ouuid' => $ouuid,
                ]);
            } else if ($category == 'asset') {

                if (empty($contentType->getAssetField()) && empty($revision->getRawData()[$contentType->getAssetField()])) {
                    throw new NotFoundHttpException('Asset field not found for ' . $revision);
                }
                return $this->redirectToRoute('file.download', [
                    'sha1' => $revision->getRawData()[$contentType->getAssetField()]['sha1'],
                    'type' => $revision->getRawData()[$contentType->getAssetField()]['mimetype'],
                    'name' => $revision->getRawData()[$contentType->getAssetField()]['filename'],
                ]);

            }
        } else {
            throw new NotFoundHttpException('Impossible to find this item : ' . $key);
        }
    }
}