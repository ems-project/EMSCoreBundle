<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Dompdf\Dompdf;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\Form\SearchFilter;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Entity\View;
use EMS\CoreBundle\Exception\DuplicateOuuidException;
use EMS\CoreBundle\Exception\ElasticmsException;
use EMS\CoreBundle\Exception\HasNotCircleException;
use EMS\CoreBundle\Exception\LockedException;
use EMS\CoreBundle\Exception\PrivilegeException;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\RenderOptionType;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Form\View\ViewType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Repository\TemplateRepository;
use EMS\CoreBundle\Repository\ViewRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\ElasticsearchService;
use EMS\CoreBundle\Service\Mapping;
use Exception;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig_Error;
use Twig_Error_Loader;
use Twig_Error_Syntax;

class DataController extends AppController
{
    /**
     * @Route("/data/{name}", name="ems_data_default_search"))
     * @Route("/data/{name}", name="data.root"))
     * @param string $name
     * @return Response
     */
    public function rootAction($name)
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
            throw new NotFoundHttpException('Content type ' . $name . ' not found');
        }


        $searchRepository = $em->getRepository('EMSCoreBundle:Form\Search');
        $searches = $searchRepository->findBy([
            'contentType' => $contentType->getId(),
        ]);
        /**@var Search $search */
        foreach ($searches as $search) {
            return $this->forward('EMSCoreBundle:Elasticsearch:search', [
                'query' => null,
            ], [
                'search_form' => $search->jsonSerialize(),
            ]);
        }


        $searchForm = new Search();
        $searchForm->setContentTypes([$contentType->getName()]);
        $searchForm->setEnvironments([$contentType->getEnvironment()->getName()]);
        $searchForm->setSortBy('_finalization_datetime');
        if ($contentType->getSortBy()) {
            $searchForm->setSortBy($contentType->getSortBy());
        }
        $searchForm->setSortOrder('desc');
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
     *
     * @param string $name
     * @return Response
     */
    public function inMyCirclesAction($name)
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
            throw new NotFoundHttpException('Content type ' . $name . ' not found');
        }

        $searchForm = new Search();
        $searchForm->setContentTypes([$contentType->getName()]);
        $searchForm->setEnvironments([$contentType->getEnvironment()->getName()]);
        $searchForm->setSortBy('_finalization_datetime');
        if ($contentType->getSortBy()) {
            $searchForm->setSortBy($contentType->getSortBy());
        }
        $searchForm->setSortOrder('desc');
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
     * @param ContentType $contentType
     * @return Response
     * @Route("/data/trash/{contentType}", name="ems_data_trash"))
     */
    public function trashAction(ContentType $contentType)
    {
        return $this->render('@EMSCore/data/trash.html.twig', [
            'contentType' => $contentType,
            'revisions' => $this->getDataService()->getAllDeleted($contentType),
        ]);
    }


    /**
     * @param ContentType $contentType
     * @param string $ouuid
     * @return RedirectResponse
     *
     * @Route("/data/put-back/{contentType}/{ouuid}", name="ems_data_put_back"), methods={"POST"})
     */
    public function putBackAction(ContentType $contentType, $ouuid)
    {
        $revId = $this->getDataService()->putBack($contentType, $ouuid);

        return $this->redirectToRoute('ems_revision_edit', [
            'revisionId' => $revId
        ]);
    }


    /**
     * @param ContentType $contentType
     * @param string $ouuid
     * @return RedirectResponse
     *
     * @Route("/data/empty-trash/{contentType}/{ouuid}", name="ems_data_empty_trash"), methods={"POST"})
     */
    public function emptyTrashAction(ContentType $contentType, $ouuid)
    {
        $this->getDataService()->emptyTrash($contentType, $ouuid);

        return $this->redirectToRoute('ems_data_trash', [
            'contentType' => $contentType->getId(),
        ]);
    }


    /**
     * @param int $contentTypeId
     * @return Response
     * @Route("/data/draft/{contentTypeId}", name="data.draft_in_progress"))
     */
    public function draftInProgressAction($contentTypeId)
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
     * @param string $environmentName
     * @param string $type
     * @param string $ouuid
     * @return Response
     * @Route("/data/view/{environmentName}/{type}/{ouuid}", name="data.view")
     */
    public function viewDataAction($environmentName, $type, $ouuid)
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

        /**@var ContentType $contentType */
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
        } catch (Throwable $e) {
            throw new NotFoundHttpException($type . ' not found');
        }

        return $this->render('@EMSCore/data/view-data.html.twig', [
            'object' => $result,
            'environment' => $environments[0],
            'contentType' => $contentType,
        ]);
    }

    /**
     * @param ContentType $contentType
     * @param string $ouuid
     * @param Environment $environment
     * @param LoggerInterface $logger
     * @return RedirectResponse
     * @Route("/data/revisions-in-environment/{environment}/{type}:{ouuid}", name="data.revision_in_environment", defaults={"deleted":0})
     * @ParamConverter("contentType", options={"mapping": {"type" = "name", "deleted" = "deleted"}})
     * @ParamConverter("environment", options={"mapping": {"environment" = "name"}})
     * @throws NonUniqueResultException
     */
    public function revisionInEnvironmentDataAction(ContentType $contentType, string $ouuid, Environment $environment, LoggerInterface $logger)
    {
        try {
            $revision = $this->getDataService()->getRevisionByEnvironment($ouuid, $contentType, $environment);
            return $this->redirectToRoute('data.revisions', [
                'type' => $contentType->getName(),
                'ouuid' => $ouuid,
                'revisionId' => $revision->getId(),
            ]);
        } catch (NoResultException $e) {
            $logger->warning('log.data.revision.not_found_in_environment', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
                EmsFields::LOG_OUUID_FIELD => $ouuid,
            ]);
            return $this->redirectToRoute('data.draft_in_progress', ['contentTypeId' => $contentType->getId()]);
        }
    }

    /**
     * @Route("/public-key" , name="ems_get_public_key")
     */
    public function publicKey() : Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/plain');
        $response->setContent($this->getDataService()->getPublicKey());
        return $response;
    }

    /**
     * @param string $type
     * @param string $ouuid
     * @param int $revisionId
     * @param int $compareId
     * @param Request $request
     * @param DataService $dataService
     * @param LoggerInterface $logger
     * @return Response
     * @throws NonUniqueResultException
     * @throws NoResultException
     *
     * @Route("/data/revisions/{type}:{ouuid}/{revisionId}/{compareId}", defaults={"revisionId": false, "compareId": false} , name="data.revisions")
     * @Route("/data/revisions/{type}:{ouuid}/{revisionId}/{compareId}", defaults={"revisionId": false, "compareId": false} , name="ems_content_revisions_view")
     */
    public function revisionsDataAction($type, $ouuid, $revisionId, $compareId, Request $request, DataService $dataService, LoggerInterface $logger)
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
        if ($compareId) {
            $logger->warning('log.data.revision.compare_beta', []);

            try {
                /**@var Revision $compareRevision */
                $compareRevision = $repository->findOneById($compareId);
                $compareData = $compareRevision->getRawData();
                if ($revision->getContentType() === $compareRevision->getContentType() && $revision->getOuuid() == $compareRevision->getOuuid()) {
                    if ($compareRevision->getCreated() <= $revision->getCreated()) {
                        $logger->notice('log.data.revision.compare', [
                            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                            'compare_revision_id' => $compareRevision->getId(),
                        ]);
                    } else {
                        $logger->warning('log.data.revision.compare_more_recent', [
                            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                            'compare_revision_id' => $compareRevision->getId(),
                        ]);
                    }
                } else {
                    $logger->notice('log.data.document.compare', [
                        EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                        EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                        EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                        'compare_contenttype' => $compareRevision->getContentType()->getName(),
                        'compare_ouuid' => $compareRevision->getOuuid(),
                        'compare_revision_id' => $compareRevision->getId(),
                    ]);
                }
            } catch (\Throwable $e) {
                $logger->warning('log.data.revision.compare_revision_not_found', [
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    'compare_revision_id' => $compareId,
                ]);
            }
        }


        if (!$revision || $revision->getOuuid() != $ouuid || $revision->getContentType() !== $contentType || $revision->getDeleted()) {
            throw new NotFoundHttpException('Revision not found');
        }

        $dataService->testIntegrityInIndexes($revision);

        $this->loadAutoSavedVersion($revision, $logger);

        $page = $request->query->get('page', 1);

        $revisionsSummary = $repository->getAllRevisionsSummary($ouuid, $contentType, $page);
        $lastPage = $repository->revisionsLastPage($ouuid, $contentType);
        $counter = $repository->countRevisions($ouuid, $contentType);
        $firstElemOfPage = $repository->firstElemOfPage($page);
        /** @var EnvironmentRepository $envRepository */
        $envRepository = $em->getRepository('EMSCoreBundle:Environment');
        $availableEnv = $envRepository->findAvailableEnvironements(
            $revision->getContentType()->getEnvironment()
        );


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

        $refParams = [
            '_source' => false,
            'type' => $searchForm->getContentTypes(),
            'index' => $revision->getContentType()->getEnvironment()->getAlias(),
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


    /**
     * @param string $environment
     * @param string $type
     * @param string $ouuid
     * @param DataService $dataService
     * @param LoggerInterface $logger
     * @return RedirectResponse
     * @throws DuplicateOuuidException
     * @Route("/data/duplicate/{environment}/{type}/{ouuid}", name="emsco_duplicate_revision"), methods={"POST"})
     */
    public function duplicateAction(string $environment, string $type, string $ouuid, DataService $dataService, LoggerInterface $logger)
    {
        $contentType = $this->getContentTypeService()->getByName($type);
        if (!$contentType) {
            throw new NotFoundHttpException('Content type ' . $type . ' not found');
        }

        $dataRaw = $this->getElasticsearch()->get([
            'index' => $this->getContentTypeService()->getIndex($contentType),
            'id' => $ouuid,
            'type' => $type,
        ]);

        if ($contentType->getAskForOuuid()) {
            $logger->warning('log.data.document.cant_duplicate_when_waiting_ouuid', [
                EmsFields::LOG_OUUID_FIELD => $ouuid,
                EmsFields::LOG_CONTENTTYPE_FIELD => $type,
            ]);

            return $this->redirectToRoute('data.view', [
                'environmentName' => $environment,
                'type' => $type,
                'ouuid' => $ouuid,
            ]);
        }

        $revision = $dataService->newDocument($contentType, null, $dataRaw['_source']);

        $logger->notice('log.data.document.duplicated', [
            EmsFields::LOG_OUUID_FIELD => $ouuid,
            EmsFields::LOG_CONTENTTYPE_FIELD => $type,
        ]);

        return $this->redirectToRoute('ems_revision_edit', [
            'revisionId' => $revision->getId()
        ]);
    }


    /**
     * @param string $environment
     * @param string $type
     * @param string $ouuid
     * @param Request $request
     * @param LoggerInterface $logger
     * @return RedirectResponse
     *
     * @Route("/data/copy/{environment}/{type}/{ouuid}", name="revision.copy"), methods={"GET"})
     */
    public function copyAction($environment, $type, $ouuid, Request $request, LoggerInterface $logger)
    {
        $contentType = $this->getContentTypeService()->getByName($type);
        if (!$contentType) {
            throw new NotFoundHttpException('Content type ' . $type . ' not found');
        }

        $dataRaw = $this->getElasticsearch()->get([
            'index' => $this->getContentTypeService()->getIndex($contentType),
            'id' => $ouuid,
            'type' => $type,
        ]);

        $request->getSession()->set('ems_clipboard', $dataRaw['_source']);

        $logger->notice('log.data.document.copy', [
            EmsFields::LOG_OUUID_FIELD => $ouuid,
            EmsFields::LOG_CONTENTTYPE_FIELD => $type,
        ]);

        return $this->redirectToRoute('data.view', [
            'environmentName' => $environment,
            'type' => $type,
            'ouuid' => $ouuid,
        ]);
    }


    /**
     * @param string $type
     * @param string $ouuid
     * @param DataService $dataService
     * @return RedirectResponse
     *
     * @Route("/data/new-draft/{type}/{ouuid}", name="revision.new-draft"), methods={"POST"})
     */
    public function newDraftAction(string $type, string $ouuid, DataService $dataService) : RedirectResponse
    {
        return $this->redirectToRoute('revision.edit', [
            'revisionId' => $dataService->initNewDraft($type, $ouuid)->getId()
        ]);
    }


    /**
     * @param string $type
     * @param string $ouuid
     * @param DataService $dataService
     * @param LoggerInterface $logger
     * @return RedirectResponse
     * @throws Missing404Exception
     * @throws Exception
     * @throws NonUniqueResultException
     * @Route("/data/delete/{type}/{ouuid}", name="object.delete"), methods={"POST"})
     */
    public function deleteAction(string $type, string $ouuid, DataService $dataService, LoggerInterface $logger)
    {
        $revision = $dataService->getNewestRevision($type, $ouuid);
        $contentType = $revision->getContentType();
        $found = false;
        foreach ($this->getEnvironmentService()->getAll() as $environment) {
            /**@var Environment $environment */
            if ($environment !== $revision->getContentType()->getEnvironment()) {
                try {
                    $sibling = $dataService->getRevisionByEnvironment($ouuid, $revision->getContentType(), $environment);
                    $logger->warning('log.data.revision.cant_delete_has_published', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                        'published_in' => $environment->getName(),
                        EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
                        EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                        EmsFields::LOG_REVISION_ID_FIELD => $sibling->getId(),
                    ]);
                    $found = true;
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

        $dataService->delete($type, $ouuid);

        return $this->redirectToRoute('data.draft_in_progress', [
            'contentTypeId' => $contentType->getId(),
        ]);
    }

    public function discardDraft(Revision $revision)
    {
        return $this->getDataService()->discardDraft($revision);
    }

    /**
     * @param int $revisionId
     * @param LoggerInterface $logger
     * @param DataService $dataService
     * @return RedirectResponse
     *
     * @throws LockedException
     * @throws PrivilegeException
     * @Route("/data/draft/discard/{revisionId}", name="revision.discard"), methods={"POST"})
     */
    public function discardRevisionAction($revisionId, LoggerInterface $logger, DataService $dataService)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Revision|null $revision */
        $revision = $repository->find($revisionId);

        if ($revision === null) {
            throw $this->createNotFoundException('Revision not found');
        }
        if (!$revision->getDraft() || null != $revision->getEndTime()) {
            throw new BadRequestHttpException('Only authorized on a draft');
        }


        $contentTypeId = $revision->getContentType()->getId();
        $type = $revision->getContentType()->getName();
        $autoPublish = $revision->getContentType()->isAutoPublish();
        $ouuid = $revision->getOuuid();

        $hasPreviousRevision = $this->discardDraft($revision);

        if (null != $ouuid && $hasPreviousRevision) {
            if ($autoPublish) {
                return $this->reindexRevisionAction($logger, $dataService, $hasPreviousRevision, true);
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
     * @param Revision $revision
     * @param DataService $dataService
     * @param LoggerInterface $logger
     * @return RedirectResponse
     * @throws LockedException
     * @throws PrivilegeException
     * @Route("/data/cancel/{revision}", name="revision.cancel"), methods={"POST"})
     */
    public function cancelModificationsAction(Revision $revision, DataService $dataService, LoggerInterface $logger) : RedirectResponse
    {
        $contentTypeId = $revision->getContentType()->getId();
        $type = $revision->getContentType()->getName();
        $ouuid = $revision->getOuuid();


        $dataService->lockRevision($revision);

        $em = $this->getDoctrine()->getManager();
        $revision->setAutoSave(null);
        $em->persist($revision);
        $em->flush();

        if (null != $ouuid) {
            if ($revision->getContentType()->isAutoPublish()) {
                $this->getPublishService()->silentPublish($revision);

                $logger->warning('log.data.revision.auto_publish_rollback', [
                    EmsFields::LOG_OUUID_FIELD => $ouuid,
                    EmsFields::LOG_CONTENTTYPE_FIELD => $type,
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    EmsFields::LOG_ENVIRONMENT_FIELD => $revision->getContentType()->getEnvironment()->getName(),
                ]);
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
     * @param LoggerInterface $logger
     * @param DataService $dataService
     * @param int $revisionId
     * @param bool $defaultOnly
     * @return RedirectResponse
     * @throws LockedException
     * @throws PrivilegeException
     * @Route("/data/revision/re-index/{revisionId}", name="revision.reindex"), methods={"POST"})
     */
    public function reindexRevisionAction(LoggerInterface $logger, DataService $dataService, $revisionId, $defaultOnly = false) : RedirectResponse
    {

        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Revision|null $revision */
        $revision = $repository->find($revisionId);

        if ($revision === null) {
            throw $this->createNotFoundException('Revision not found');
        }

        $dataService->lockRevision($revision);

        /** @var Client $client */
        $client = $this->getElasticsearch();


        try {
            $this->getDataService()->reloadData($revision);

            $objectArray = $this->getDataService()->sign($revision);


            $objectArray[Mapping::PUBLISHED_DATETIME_FIELD] = (new DateTime())->format(DateTime::ISO8601);

            /** @var Environment $environment */
            foreach ($revision->getEnvironments() as $environment) {
                if (!$defaultOnly || $environment === $revision->getContentType()->getEnvironment()) {
                    $index = $this->getContentTypeService()->getIndex($revision->getContentType(), $environment);

                    $result = $client->index([
                        'id' => $revision->getOuuid(),
                        'index' => $index,
                        'type' => $revision->getContentType()->getName(),
                        'body' => $objectArray
                    ]);
                    if (isset($result['_shards']['successful']) && $result['_shards']['successful'] > 0) {
                        $logger->notice('log.data.revision.reindex', [
                            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                            EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                        ]);
                    } else {
                        $logger->warning('log.data.revision.reindex_failed_in', [
                            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                            EmsFields::LOG_ENVIRONMENT_FIELD => $environment->getName(),
                            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                        ]);
                    }
                }
            }
            $em->persist($revision);
            $em->flush();
        } catch (Exception $e) {
            $logger->warning('log.data.revision.reindex_failed', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
            ]);
        }
        return $this->redirectToRoute('data.revisions', [
            'ouuid' => $revision->getOuuid(),
            'type' => $revision->getContentType()->getName(),
            'revisionId' => $revision->getId(),
        ]);
    }

    /**
     * @param int $viewId
     * @param bool $public
     * @param Request $request
     * @param TranslatorInterface $translator
     * @return mixed
     *
     * @Route("/public/view/{viewId}", name="ems_custom_view_public", defaults={"public": true})
     * @Route("/data/custom-index-view/{viewId}", name="data.customindexview", defaults={"public": false})
     * @Route("/data/custom-index-view/{viewId}", name="ems_custom_view_protected", defaults={"public": false})
     */
    public function customIndexViewAction($viewId, $public, Request $request, TranslatorInterface $translator)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var ViewRepository $viewRepository */
        $viewRepository = $em->getRepository('EMSCoreBundle:View');

        /** @var View|null $view * */
        $view = $viewRepository->find($viewId);

        if ($view === null || ($public && !$view->isPublic())) {
            throw new NotFoundHttpException($translator->trans('log.view.not_found', [
                '%view_id%' => $viewId,
            ], EMSCoreBundle::TRANS_DOMAIN));
        }

        /** @var ViewType $viewType */
        $viewType = $this->get($view->getType());

        return $viewType->generateResponse($view, $request);
    }

    /**
     * @param string $environmentName
     * @param int $templateId
     * @param string $ouuid
     * @param bool $_download
     * @param bool $public
     * @param LoggerInterface $logger
     * @param TranslatorInterface $translator
     * @return Response
     * @throws Throwable
     * @throws LoaderError
     * @throws SyntaxError
     * @throws Twig_Error_Loader
     * @throws Twig_Error_Syntax
     * @Route("/public/template/{environmentName}/{templateId}/{ouuid}/{_download}", defaults={"_download": false, "public": true} , name="ems_data_custom_template_public"))
     * @Route("/data/custom-view/{environmentName}/{templateId}/{ouuid}/{_download}", defaults={"_download": false, "public": false} , name="data.customview"))
     * @Route("/data/template/{environmentName}/{templateId}/{ouuid}/{_download}", defaults={"_download": false, "public": false} , name="ems_data_custom_template_protected"))
     */
    public function customViewAction($environmentName, $templateId, $ouuid, $_download, $public, LoggerInterface $logger, TranslatorInterface $translator)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var TemplateRepository $templateRepository */
        $templateRepository = $em->getRepository('EMSCoreBundle:Template');

        /** @var Template|null $template * */
        $template = $templateRepository->find($templateId);

        if ($template === null || ($public && !$template->isPublic())) {
            throw new NotFoundHttpException('Template type not found');
        }

        /** @var EnvironmentRepository $environmentRepository */
        $environmentRepository = $em->getRepository('EMSCoreBundle:Environment');

        $environment = $environmentRepository->findBy([
            'name' => $environmentName,
        ]);

        if (!$environment || count($environment) != 1) {
            throw new NotFoundHttpException('Environment type not found');
        }

        /** @var Environment $environment */
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
            $body = $twig->createTemplate($template->getBody());
        } catch (Twig_Error $e) {
            $logger->error('log.template.twig.error', [
                'template_id' => $template->getId(),
                'template_name' => $template->getName(),
            ]);
            $body = $twig->createTemplate($translator->trans('log.template.twig.error', [
                '%template_id%' => $template->getId(),
                '%template_name%' => $template->getName(),
            ], EMSCoreBundle::TRANS_DOMAIN));
        }

        if ($template->getRenderOption() === RenderOptionType::PDF && ($_download || !$template->getPreview())) {
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
            $dompdf->setPaper($template->getSize() ? $template->getSize() : 'A3', $template->getOrientation() ? $template->getOrientation() : 'portrait');

            // Render the HTML as PDF
            $dompdf->render();

            // Output the generated PDF to Browser
            $dompdf->stream($template->getFilename() ?? "document.pdf", [
                'compress' => 1,
                'Attachment' => ($template->getDisposition() && $template->getDisposition() === 'attachment')?1:0,
            ]);
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
                } catch (Twig_Error $e) {
                    $logger->error('log.template.twig.error', [
                        'template_id' => $template->getId(),
                        'template_name' => $template->getName(),
                    ]);
                    $body = $twig->createTemplate($translator->trans('log.template.twig.error', [
                        '%template_id%' => $template->getId(),
                        '%template_name%' => $template->getName(),
                    ], EMSCoreBundle::TRANS_DOMAIN));
                }

                $filename = $filename->render([
                    'environment' => $environment,
                    'contentType' => $template->getContentType(),
                    'object' => $object,
                    'source' => $object['_source'],
                ]);
                $filename = preg_replace('~[\r\n]+~', '', $filename);
            }


            if (!empty($template->getDisposition())) {
                $attachment = ResponseHeaderBag::DISPOSITION_ATTACHMENT;
                if ($template->getDisposition() == 'inline') {
                    $attachment = ResponseHeaderBag::DISPOSITION_INLINE;
                }
                header("Content-Disposition: $attachment; filename=" . $filename . ($template->getExtension() ? '.' . $template->getExtension() : ''));
            }
            if (null != $template->getAllowOrigin()) {
                header("Access-Control-Allow-Origin: " . $template->getAllowOrigin());
                header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, Accept-Language, If-None-Match, If-Modified-Since');
                header('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS');
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
     * @param string $environmentName
     * @param int $templateId
     * @param string $ouuid
     * @param LoggerInterface $logger
     * @param ElasticsearchService $esService
     * @return Response
     * @throws Throwable
     * @Route("/data/custom-view-job/{environmentName}/{templateId}/{ouuid}", name="ems_job_custom_view", methods={"POST"})
     */
    public function customViewJobAction($environmentName, $templateId, $ouuid, LoggerInterface $logger, ElasticsearchService $esService, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var Template|null $template * */
        $template = $em->getRepository(Template::class)->find($templateId);
        /** @var Environment|null $env */
        $env = $em->getRepository(Environment::class)->findOneByName($environmentName);

        if ($template === null || $env === null) {
            throw new NotFoundHttpException();
        }

        $document = $esService->get($env, $template->getContentType(), $ouuid);

        $success = false;
        try {
            $command = $this->getTwig()->createTemplate($template->getBody())->render([
                'environment' => $env->getName(),
                'contentType' => $template->getContentType(),
                'object' => $document,
                'source' => $document->getSource(),
            ]);

            /** @var CoreBundle\Service\JobService $jobService */
            $jobService = $this->get('ems.service.job');
            $job = $jobService->createCommand($this->getUser(), $command);

            $success = true;
            $logger->notice('log.data.job.initialized', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $template->getContentType()->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                EmsFields::LOG_OUUID_FIELD => $ouuid,
                'template_id' => $template->getId(),
                'job_id' => $job->getId(),
                'template_name' => $template->getName(),
            ]);
            return $this->returnJsonResponse($request, true, [
                'jobId' => $job->getId(),
                'jobUrl' => $this->generateUrl('emsco_job_start', ['job' => $job->getId()], UrlGeneratorInterface::RELATIVE_PATH),
            ]);
        } catch (Exception $e) {
            $logger->error('log.data.job.initialize_failed', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $template->getContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $ouuid,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
            ]);
        }

        return $this->returnJson($success);
    }

    /**
     * @param int $revisionId
     * @param Request $request
     * @param DataService $dataService
     * @param LoggerInterface $logger
     * @return Response
     * @throws LockedException
     * @throws PrivilegeException
     * @throws Exception
     * @Route("/data/revision/{revisionId}.json", name="revision.ajaxupdate"), defaults={"_format": "json"}, methods={"POST"}))
     */
    public function ajaxUpdateAction($revisionId, Request $request, DataService $dataService, LoggerInterface $logger)
    {

        $em = $this->getDoctrine()->getManager();
        $formErrors = [];

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Revision|null $revision */
        $revision = $repository->find($revisionId);

        if ($revision === null) {
            throw new NotFoundHttpException('Revision not found');
        }

        if (!$revision->getDraft() || $revision->getEndTime() !== null) {
            $logger->warning('log.data.revision.ajax_update_on_finalized', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
            ]);

            $response = $this->render('@EMSCore/ajax/notification.json.twig', [
                'success' => false,
            ]);
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        if (empty($request->request->get('revision')) || empty($request->request->get('revision')['allFieldsAreThere']) || !$request->request->get('revision')['allFieldsAreThere']) {
            $logger->error('log.data.revision.not_completed_request', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
            ]);
        } else {
            $dataService->lockRevision($revision);
            $this->getLogger()->debug('Revision locked');

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
            $objectArray = $revision->getRawData();
            $revision->setRawData($backup);


            $revision->setAutoSaveAt(new DateTime());
            $revision->setAutoSaveBy($this->getUser()->getUsername());

            $em->persist($revision);
            $em->flush();

            $this->getDataService()->isValid($form);
            $this->getDataService()->propagateDataToComputedField($form->get('data'), $objectArray, $revision->getContentType(), $revision->getContentType()->getName(), $revision->getOuuid());
            $session = $request->getSession();
            if ($session instanceof Session) {
                $session->getFlashBag()->set('warning', []);
            }

            $formErrors = $form->getErrors(true, true);

            if ($formErrors->count() === 0 && $revision->getContentType()->isAutoPublish()) {
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
     * @param Revision $revision
     * @param LoggerInterface $logger
     * @return RedirectResponse|Response
     * @Route("/data/draft/finalize/{revision}", name="revision.finalize"), methods={"POST"})
     */
    public function finalizeDraftAction(Revision $revision, LoggerInterface $logger)
    {

        $this->getDataService()->loadDataStructure($revision);
        try {
            $form = $this->createForm(RevisionType::class, $revision, ['raw_data' => $revision->getRawData()]);
            if (!empty($revision->getAutoSave())) {
                $logger->error('log.data.revision.can_finalized_as_pending_auto_save', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                ]);
                return $this->redirectToRoute('revision.edit', [
                    'revisionId' => $revision->getId(),
                ]);
            }

            $revision = $this->getDataService()->finalizeDraft($revision, $form);
            if (count($form->getErrors()) !== 0) {
                $logger->error('log.data.revision.can_finalized_as_invalid', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    'count' => $form->getErrors(true)->count(),
                ]);
                return $this->redirectToRoute('revision.edit', [
                    'revisionId' => $revision->getId(),
                ]);
            }
        } catch (Exception $e) {
            $logger->error('log.data.revision.can_finalized_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
            ]);
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

    /**
     * @param int $revisionId
     * @param Request $request
     * @param LoggerInterface $logger
     * @param DataService $dataService
     * @param TranslatorInterface $translator
     * @return RedirectResponse|Response
     * @throws CoreBundle\Exception\DataStateException
     * @throws ElasticmsException
     * @throws LockedException
     * @throws PrivilegeException
     * @throws Exception
     * @Route("/data/draft/edit/{revisionId}", name="ems_revision_edit"))
     * @Route("/data/draft/edit/{revisionId}", name="revision.edit"))
     */
    public function editRevisionAction($revisionId, Request $request, LoggerInterface $logger, DataService $dataService, TranslatorInterface $translator)
    {
        $em = $this->getDoctrine()->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Revision|null $revision */
        $revision = $repository->find($revisionId);

        if ($revision === null) {
            throw new NotFoundHttpException('Unknown revision');
        }

        $dataService->lockRevision($revision);

        if ($revision->getEndTime() &&! $this->isGranted('ROLE_SUPER')) {
            throw new ElasticmsException($translator->trans('log.data.revision.only_super_can_finalize_an_archive', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
            ], EMSCoreBundle::TRANS_DOMAIN));
        }

        if ($request->isMethod('GET')) {
            $this->loadAutoSavedVersion($revision, $logger);
        }

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
                $logger->error('log.data.revision.not_completed_request', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                ]);

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
                    $logger->notice('log.data.revision.paste', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                        EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                        EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                        EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    ]);
                    $objectArray = array_merge($objectArray, $request->getSession()->get('ems_clipboard', []));
                    $this->getLogger()->debug('Paste data have been merged');
                }

                if (array_key_exists('copy', $request->request->get('revision'))) {//Copy
                    $request->getSession()->set('ems_clipboard', $objectArray);
                    $logger->notice('log.data.document.copy', [
                        EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                        EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                        EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                        EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    ]);
                }

                $revision->setRawData($objectArray);


                $this->getDataService()->setMetaFields($revision);

                $this->getLogger()->debug('Revision before persist');
                $em->persist($revision);
                $em->flush();

                $this->getLogger()->debug('Revision after persist flush');

                if (array_key_exists('publish', $request->request->get('revision'))) {//Finalize
                    $revision = $dataService->finalizeDraft($revision, $form);
                    if (count($form->getErrors()) === 0) {
                        if ($revision->getOuuid()) {
                            return $this->redirectToRoute('data.revisions', [
                                'ouuid' => $revision->getOuuid(),
                                'type' => $revision->getContentType()->getName(),
                            ]);
                        } else {
                            return $this->redirectToRoute('revision.edit', [
                                'revisionId' => $revision->getId(),
                            ]);
                        }
                    }
                }
            }

            //if paste or copy
            if (array_key_exists('paste', $request->request->get('revision')) || array_key_exists('copy', $request->request->get('revision'))) {//Paste or copy
                return $this->redirectToRoute('revision.edit', [
                    'revisionId' => $revisionId,
                ]);
            }

            //if Save or Discard
            if (!array_key_exists('publish', $request->request->get('revision'))) {
                if (null != $revision->getOuuid()) {
                    if (count($form->getErrors()) === 0 && $revision->getContentType()->isAutoPublish()) {
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
            }
        } else {
            $isValid = $this->getDataService()->isValid($form);
            if (!$isValid) {
                $logger->warning('log.data.revision.can_finalized', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                ]);
            }
        }

        if ($revision->getContentType()->isAutoPublish()) {
            $logger->warning('log.data.revision.auto_save_off_with_auto_publish', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                EmsFields::LOG_ENVIRONMENT_FIELD => $revision->getContentType()->getEnvironment()->getName(),
            ]);
        }


        $objectArray = $revision->getRawData();
        $this->getDataService()->propagateDataToComputedField($form->get('data'), $objectArray, $revision->getContentType(), $revision->getContentType()->getName(), $revision->getOuuid());

        if ($revision->getOuuid()) {
            $messageLog = "log.data.revision.start_edit";
        } else {
            $messageLog = "log.data.revision.start_edit_new_document";
        }
        $logger->info($messageLog, [
            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
        ]);

        return $this->render('@EMSCore/data/edit-revision.html.twig', [
            'revision' => $revision,
            'form' => $form->createView(),
            'stylesSets' => $this->getWysiwygStylesSetService()->getStylesSets(),
        ]);
    }

    /**
     * @param ContentType $contentType
     * @param Request $request
     * @param DataService $dataService
     * @return RedirectResponse|Response
     * @throws HasNotCircleException
     * @Route("/data/add/{contentType}", name="data.add"))
     */
    public function addAction(ContentType $contentType, Request $request, DataService $dataService)
    {
        $dataService->hasCreateRights($contentType);

        $revision = new Revision();
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
            try {
                $revision = $dataService->newDocument($contentType, $revision->getOuuid());

                return $this->redirectToRoute('revision.edit', [
                    'revisionId' => $revision->getId()
                ]);
            } catch (DuplicateOuuidException $e) {
                $form->get('ouuid')->addError(new FormError('Another ' . $contentType->getName() . ' with this identifier already exists'));
            }
        }

        return $this->render('@EMSCore/data/add.html.twig', [
            'contentType' => $contentType,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param Revision $revision
     * @param DataService $dataService
     * @param LoggerInterface $logger
     * @return RedirectResponse
     * @throws ElasticmsException
     * @throws Exception
     * @Route("/data/revisions/revert/{id}", name="revision.revert"), methods={"POST"}))
     */
    public function revertRevisionAction(Revision $revision, DataService $dataService, LoggerInterface $logger)
    {
        $type = $revision->getContentType()->getName();
        $ouuid = $revision->getOuuid();


        $newestRevision = $dataService->getNewestRevision($type, $ouuid);
        if ($newestRevision->getDraft()) {
            throw new ElasticmsException('Can\`t revert if a  draft exists for the document');
        }

        $revertedRevision = $dataService->initNewDraft($type, $ouuid, $revision);
        $logger->notice('log.data.revision.new_draft_from_revision', [
            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
            EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
        ]);

        return $this->redirectToRoute('revision.edit', [
            'revisionId' => $revertedRevision->getId()
        ]);
    }

    /**
     * @param string $key
     * @return RedirectResponse
     * @throws NonUniqueResultException
     * @Route("/data/link/{key}", name="data.link")
     */
    public function linkDataAction($key)
    {
        $category = $type = $ouuid = null;
        $split = explode(':', $key);
        if ($split && count($split) == 3) {
            $category = $split[0]; // object or asset
            $type = $split[1];
            $ouuid = $split[2];
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
            }
            if ($category == 'asset') {
                if (empty($contentType->getAssetField()) && empty($revision->getRawData()[$contentType->getAssetField()])) {
                    throw new NotFoundHttpException('Asset field not found for ' . $revision);
                }

                return $this->redirectToRoute('file.download', [
                    'sha1' => $revision->getRawData()[$contentType->getAssetField()]['sha1'],
                    'type' => $revision->getRawData()[$contentType->getAssetField()]['mimetype'],
                    'name' => $revision->getRawData()[$contentType->getAssetField()]['filename'],
                ]);
            }
        }
        throw new NotFoundHttpException('Impossible to find this item : ' . $key);
    }

    private function loadAutoSavedVersion(Revision $revision, LoggerInterface $logger)
    {
        if (null != $revision->getAutoSave()) {
            $revision->setRawData($revision->getAutoSave());
            $logger->warning('log.data.revision.load_from_auto_save', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->getContentType()->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
            ]);
        }
    }

    private function reorderCollection(&$input)
    {
        if (is_array($input) && !empty($input)) {
            $keys = array_keys($input);
            if (is_int($keys[0])) {
                sort($keys);
                $temp = [];
                $loop0 = 0;
                foreach ($input as $item) {
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
}
