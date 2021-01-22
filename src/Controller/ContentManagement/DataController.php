<?php

namespace EMS\CoreBundle\Controller\ContentManagement;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Dompdf\Dompdf;
use EMS\CommonBundle\Elasticsearch\Response\Response as CommonResponse;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Controller\AppController;
use EMS\CoreBundle\EMSCoreBundle;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Environment;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\Form\SearchFilter;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Entity\Template;
use EMS\CoreBundle\Entity\UserInterface;
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
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\IndexService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\PublishService;
use EMS\CoreBundle\Service\SearchService;
use EMS\CoreBundle\Service\UserService;
use Psr\Container\ContainerInterface;
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
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as TwigEnvironment;
use Twig\Error\Error;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;

class DataController extends AppController
{
    /**
     * @Route("/data/{name}", name="ems_data_default_search")
     * @Route("/data/{name}", name="data.root")
     *
     * @param string $name
     *
     * @return Response
     */
    public function rootAction($name)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var ContentTypeRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:ContentType');
        /** @var ContentType $contentType */
        $contentType = $repository->findOneBy([
            'name' => $name,
            'deleted' => false,
        ]);

        if (!$contentType) {
            throw new NotFoundHttpException('Content type '.$name.' not found');
        }

        $searchRepository = $em->getRepository('EMSCoreBundle:Form\Search');
        $searches = $searchRepository->findBy([
            'contentType' => $contentType->getId(),
        ]);
        /** @var Search $search */
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

        return $this->forward('EMS\CoreBundle\Controller\ElasticsearchController::searchAction', [
            'query' => null,
        ], [
            'search_form' => $searchForm->jsonSerialize(),
        ]);
    }

    /**
     * @Route("/data/in-my-circles/{name}", name="ems_search_in_my_circles")
     *
     * @param string $name
     *
     * @return Response
     */
    public function inMyCirclesAction($name)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var ContentTypeRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:ContentType');
        /** @var ContentType $contentType */
        $contentType = $repository->findOneBy([
            'name' => $name,
            'deleted' => false,
        ]);

        if (!$contentType) {
            throw new NotFoundHttpException('Content type '.$name.' not found');
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
            'search_form' => \json_decode(\json_encode($searchForm), true),
        ]);
    }

    /**
     * @return Response
     * @Route("/data/trash/{contentType}", name="ems_data_trash")
     */
    public function trashAction(ContentType $contentType, DataService $dataService)
    {
        return $this->render('@EMSCore/data/trash.html.twig', [
            'contentType' => $contentType,
            'revisions' => $dataService->getAllDeleted($contentType),
        ]);
    }

    /**
     * @param string $ouuid
     *
     * @return RedirectResponse
     *
     * @Route("/data/put-back/{contentType}/{ouuid}", name="ems_data_put_back", methods={"POST"})
     */
    public function putBackAction(ContentType $contentType, $ouuid, DataService $dataService)
    {
        $revId = $dataService->putBack($contentType, $ouuid);

        return $this->redirectToRoute('ems_revision_edit', [
            'revisionId' => $revId,
        ]);
    }

    /**
     * @param string $ouuid
     *
     * @return RedirectResponse
     *
     * @Route("/data/empty-trash/{contentType}/{ouuid}", name="ems_data_empty_trash", methods={"POST"})
     */
    public function emptyTrashAction(ContentType $contentType, $ouuid, DataService $dataService)
    {
        $dataService->emptyTrash($contentType, $ouuid);

        return $this->redirectToRoute('ems_data_trash', [
            'contentType' => $contentType->getId(),
        ]);
    }

    /**
     * @param int $contentTypeId
     *
     * @return Response
     * @Route("/data/draft/{contentTypeId}", name="data.draft_in_progress")
     */
    public function draftInProgressAction($contentTypeId, UserService $userService, AuthorizationCheckerInterface $authorizationChecker)
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

        $revisions = $revisionRep->findInProgresByContentType($contentType, $userService->getCurrentUser()->getCircles(), $authorizationChecker->isGranted('ROLE_USER_MANAGEMENT'));

        return $this->render('@EMSCore/data/draft-in-progress.html.twig', [
            'contentType' => $contentType,
            'revisions' => $revisions,
        ]);
    }

    /**
     * @Route("/data/view/{environmentName}/{type}/{ouuid}", name="data.view")
     */
    public function viewDataAction(string $environmentName, string $type, string $ouuid, SearchService $searchService, ContentTypeService $contentTypeService, EnvironmentService $environmentService): Response
    {
        $environment = $environmentService->getByName($environmentName);
        if (false === $environment) {
            throw new NotFoundHttpException(\sprintf('Environment %s not found', $environmentName));
        }

        $contentType = $contentTypeService->getByName($type);
        if (false === $contentType) {
            throw new NotFoundHttpException(\sprintf('Content type %s not found', $type));
        }

        try {
            $document = $searchService->getDocument($contentType, $ouuid, $environment);
        } catch (\Throwable $e) {
            throw new NotFoundHttpException(\sprintf('Document %s with identifier %s not found in environment %s', $contentType->getSingularName(), $ouuid, $environmentName));
        }

        return $this->render('@EMSCore/data/view-data.html.twig', [
            'object' => $document->getRaw(),
            'environment' => $environment,
            'contentType' => $contentType,
        ]);
    }

    /**
     * @return RedirectResponse
     * @Route("/data/revisions-in-environment/{environment}/{type}:{ouuid}", name="data.revision_in_environment", defaults={"deleted"=0})
     * @ParamConverter("contentType", options={"mapping": {"type" = "name", "deleted" = "deleted"}})
     * @ParamConverter("environment", options={"mapping": {"environment" = "name"}})
     *
     * @throws NonUniqueResultException
     */
    public function revisionInEnvironmentDataAction(ContentType $contentType, string $ouuid, Environment $environment, LoggerInterface $logger, DataService $dataService)
    {
        try {
            $revision = $dataService->getRevisionByEnvironment($ouuid, $contentType, $environment);

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
    public function publicKey(DataService $dataService): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/plain');
        $response->setContent($dataService->getPublicKey());

        return $response;
    }

    /**
     * @param string $type
     * @param string $ouuid
     * @param int    $revisionId
     * @param int    $compareId
     *
     * @return Response
     *
     * @throws NonUniqueResultException
     * @throws NoResultException
     *
     * @Route("/data/revisions/{type}:{ouuid}/{revisionId}/{compareId}", defaults={"revisionId"=false, "compareId"=false}, name="data.revisions")
     * @Route("/data/revisions/{type}:{ouuid}/{revisionId}/{compareId}", defaults={"revisionId"=false, "compareId"=false}, name="ems_content_revisions_view")
     */
    public function revisionsDataAction($type, $ouuid, $revisionId, $compareId, Request $request, DataService $dataService, LoggerInterface $logger, SearchService $searchService, ElasticaService $elasticaService, ContentTypeService $contentTypeService)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var ContentTypeRepository $contentTypeRepo */
        $contentTypeRepo = $em->getRepository('EMSCoreBundle:ContentType');

        $contentTypes = $contentTypeRepo->findBy([
            'deleted' => false,
            'name' => $type,
        ]);
        if (!$contentTypes || 1 != \count($contentTypes)) {
            throw new NotFoundHttpException('Content Type not found');
        }
        /** @var ContentType $contentType */
        $contentType = $contentTypes[0];

        $defaultEnvironment = $contentType->getEnvironment();
        if (null === $defaultEnvironment) {
            throw new \RuntimeException('Unexpected nul environment');
        }

        if (!$defaultEnvironment->getManaged()) {
            return $this->redirectToRoute('data.view', [
                'environmentName' => $defaultEnvironment->getName(),
                'type' => $type,
                'ouuid' => $ouuid,
            ]);
        }

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');

        /* @var Revision $revision */
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
                /** @var Revision $compareRevision */
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

        $dataFields = $dataService->getDataFieldsStructure($form->get('data'));

        $searchForm = new Search();
        $searchForm->setContentTypes($contentTypeService->getAllNames());
        $searchForm->setEnvironments([$defaultEnvironment->getName()]);
        $searchForm->setSortBy('_uid');
        $searchForm->setSortOrder('asc');

        $filter = $searchForm->getFilters()[0];
        $filter->setBooleanClause('should');
        $filter->setField($contentType->getRefererFieldName());
        $filter->setPattern(\sprintf('%s:%s', $type, $ouuid));
        $filter->setOperator('term');

        $filter = new SearchFilter();
        $filter->setBooleanClause('should');
        $filter->setField($contentType->getRefererFieldName());
        $filter->setPattern(\sprintf('"%s:%s"', $type, $ouuid));
        $filter->setOperator('match_and');
        $searchForm->addFilter($filter);

        $searchForm->setMinimumShouldMatch(1);
        $esSearch = $searchService->generateSearch($searchForm);
        $esSearch->setSize(100);
        $esSearch->setSources([]);

        $referrerResultSet = $elasticaService->search($esSearch);
        $referrerResponse = CommonResponse::fromResultSet($referrerResultSet);

        return $this->render('@EMSCore/data/revisions-data.html.twig', [
            'revision' => $revision,
            'revisionsSummary' => $revisionsSummary,
            'availableEnv' => $availableEnv,
            'object' => $revision->getObject($objectArray),
            'referrerResponse' => $referrerResponse,
            'page' => $page,
            'lastPage' => $lastPage,
            'counter' => $counter,
            'firstElemOfPage' => $firstElemOfPage,
            'dataFields' => $dataFields,
            'compareData' => $compareData,
            'compareId' => $compareId,
            'referrersForm' => $searchForm,
        ]);
    }

    /**
     * @return RedirectResponse
     *
     * @throws DuplicateOuuidException
     * @Route("/data/duplicate/{environment}/{type}/{ouuid}", name="emsco_duplicate_revision", methods={"POST"})
     */
    public function duplicateAction(string $environment, string $type, string $ouuid, DataService $dataService, LoggerInterface $logger, ContentTypeService $contentTypeService, EnvironmentService $environmentService)
    {
        $contentType = $contentTypeService->getByName($type);
        if (false === $contentType) {
            throw new NotFoundHttpException(\sprintf('Content type %s not found', $type));
        }
        $environmentObject = $environmentService->getByName($environment);
        if (false === $environmentObject) {
            throw new NotFoundHttpException(\sprintf('Environment %s not found', $environment));
        }

        try {
            $dataRaw = $dataService->getRevisionByEnvironment($ouuid, $contentType, $environmentObject)->getRawData();
        } catch (NoResultException $e) {
            throw new NotFoundHttpException(\sprintf('Revision %s not found', $ouuid));
        }

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

        $revision = $dataService->newDocument($contentType, null, $dataRaw);

        $logger->notice('log.data.document.duplicated', [
            EmsFields::LOG_OUUID_FIELD => $ouuid,
            EmsFields::LOG_CONTENTTYPE_FIELD => $type,
        ]);

        return $this->redirectToRoute('ems_revision_edit', [
            'revisionId' => $revision->getId(),
        ]);
    }

    /**
     * @Route("/data/copy/{environment}/{type}/{ouuid}", name="revision.copy", methods={"GET"})
     */
    public function copyAction(string $environment, string $type, string $ouuid, Request $request, LoggerInterface $logger, DataService $dataService, ContentTypeService $contentTypeService, EnvironmentService $environmentService): RedirectResponse
    {
        $contentType = $contentTypeService->getByName($type);
        if (!$contentType) {
            throw new NotFoundHttpException('Content type '.$type.' not found');
        }
        $environmentObject = $environmentService->getByName($environment);
        if (false === $environmentObject) {
            throw new NotFoundHttpException(\sprintf('Environment %s not found', $environment));
        }

        try {
            $dataRaw = $dataService->getRevisionByEnvironment($ouuid, $contentType, $environmentObject)->getRawData();
        } catch (NoResultException $e) {
            throw new NotFoundHttpException(\sprintf('Revision %s not found', $ouuid));
        }

        $request->getSession()->set('ems_clipboard', $dataRaw);

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
     * @Route("/data/new-draft/{type}/{ouuid}", name="revision.new-draft")
     */
    public function newDraftAction(string $type, string $ouuid, DataService $dataService): RedirectResponse
    {
        return $this->redirectToRoute('revision.edit', [
            'revisionId' => $dataService->initNewDraft($type, $ouuid)->getId(),
        ]);
    }

    /**
     * @return RedirectResponse
     *
     * @Route("/data/delete/{type}/{ouuid}", name="object.delete", methods={"POST"})
     */
    public function deleteAction(string $type, string $ouuid, DataService $dataService, LoggerInterface $logger, EnvironmentService $environmentService)
    {
        $revision = $dataService->getNewestRevision($type, $ouuid);
        $contentType = $revision->getContentType();
        $found = false;
        foreach ($environmentService->getAll() as $environment) {
            /** @var Environment $environment */
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

        return $this->redirectToRoute('data.root', [
            'name' => $type,
        ]);
    }

    public function discardDraft(Revision $revision, DataService $dataService): ?int
    {
        return $dataService->discardDraft($revision);
    }

    /**
     * @param int $revisionId
     *
     * @return RedirectResponse
     *
     * @throws LockedException
     * @throws PrivilegeException
     * @Route("/data/draft/discard/{revisionId}", name="revision.discard", methods={"POST"})
     */
    public function discardRevisionAction($revisionId, LoggerInterface $logger, DataService $dataService, IndexService $indexService)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Revision|null $revision */
        $revision = $repository->find($revisionId);

        if (null === $revision) {
            throw $this->createNotFoundException('Revision not found');
        }
        if (!$revision->getDraft() || null != $revision->getEndTime()) {
            throw new BadRequestHttpException('Only authorized on a draft');
        }

        $contentTypeId = $revision->getContentType()->getId();
        $type = $revision->getContentType()->getName();
        $autoPublish = $revision->getContentType()->isAutoPublish();
        $ouuid = $revision->getOuuid();

        $previousRevisionId = $this->discardDraft($revision, $dataService);

        if (null != $ouuid && null !== $previousRevisionId && $previousRevisionId > 0) {
            if ($autoPublish) {
                return $this->reindexRevisionAction($logger, $dataService, $indexService, $previousRevisionId, true);
            }

            return $this->redirectToRoute('data.revisions', [
                'type' => $type,
                'ouuid' => $ouuid,
            ]);
        }

        return $this->redirectToRoute('data.draft_in_progress', [
            'contentTypeId' => $contentTypeId,
        ]);
    }

    /**
     * @throws LockedException
     * @throws PrivilegeException
     * @Route("/data/cancel/{revision}", name="revision.cancel", methods={"POST"})
     */
    public function cancelModificationsAction(Revision $revision, DataService $dataService, LoggerInterface $logger, PublishService $publishService): RedirectResponse
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
                $publishService->silentPublish($revision);

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
            'contentTypeId' => $contentTypeId,
        ]);
    }

    /**
     * @Route("/data/revision/re-index/{revisionId}", name="revision.reindex", methods={"POST"})
     */
    public function reindexRevisionAction(LoggerInterface $logger, DataService $dataService, IndexService $indexService, int $revisionId, bool $defaultOnly = false): RedirectResponse
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Revision|null $revision */
        $revision = $repository->find($revisionId);

        if (null === $revision) {
            throw $this->createNotFoundException('Revision not found');
        }

        $dataService->lockRevision($revision);

        try {
            $dataService->reloadData($revision);
            $dataService->sign($revision);

            /** @var Environment $environment */
            foreach ($revision->getEnvironments() as $environment) {
                if (!$defaultOnly || $environment === $revision->getContentType()->getEnvironment()) {
                    if ($indexService->indexRevision($revision, $environment)) {
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
        } catch (\Throwable $e) {
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
     * @param int  $viewId
     * @param bool $public
     *
     * @return mixed
     *
     * @Route("/public/view/{viewId}", name="ems_custom_view_public", defaults={"public"=true})
     * @Route("/data/custom-index-view/{viewId}", name="data.customindexview", defaults={"public"=false})
     * @Route("/data/custom-index-view/{viewId}", name="ems_custom_view_protected", defaults={"public"=false})
     */
    public function customIndexViewAction($viewId, $public, Request $request, TranslatorInterface $translator, ContainerInterface $container)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var ViewRepository $viewRepository */
        $viewRepository = $em->getRepository('EMSCoreBundle:View');

        /** @var View|null $view * */
        $view = $viewRepository->find($viewId);

        if (null === $view || ($public && !$view->isPublic())) {
            throw new NotFoundHttpException($translator->trans('log.view.not_found', ['%view_id%' => $viewId], EMSCoreBundle::TRANS_DOMAIN));
        }
        /** @var ViewType $viewType */
        $viewType = $container->get($view->getType());

        return $viewType->generateResponse($view, $request);
    }

    /**
     * @param string $environmentName
     * @param int    $templateId
     * @param string $ouuid
     * @param bool   $_download
     * @param bool   $public
     *
     * @return Response
     *
     * @throws \Throwable
     * @throws LoaderError
     * @throws SyntaxError
     * @Route("/public/template/{environmentName}/{templateId}/{ouuid}/{_download}", defaults={"_download"=false, "public"=true}, name="ems_data_custom_template_public")
     * @Route("/data/custom-view/{environmentName}/{templateId}/{ouuid}/{_download}", defaults={"_download"=false, "public"=false}, name="data.customview")
     * @Route("/data/template/{environmentName}/{templateId}/{ouuid}/{_download}", defaults={"_download"=false, "public"=false}, name="ems_data_custom_template_protected")
     */
    public function customViewAction($environmentName, $templateId, $ouuid, $_download, $public, LoggerInterface $logger, TranslatorInterface $translator, SearchService $searchService, TwigEnvironment $twig)
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();

        /** @var TemplateRepository $templateRepository */
        $templateRepository = $em->getRepository('EMSCoreBundle:Template');

        /** @var Template|null $template * */
        $template = $templateRepository->find($templateId);

        if (null === $template || ($public && !$template->isPublic())) {
            throw new NotFoundHttpException('Template type not found');
        }

        /** @var EnvironmentRepository $environmentRepository */
        $environmentRepository = $em->getRepository('EMSCoreBundle:Environment');

        $environment = $environmentRepository->findBy([
            'name' => $environmentName,
        ]);

        if (!$environment || 1 != \count($environment)) {
            throw new NotFoundHttpException('Environment type not found');
        }

        /** @var Environment $environment */
        $environment = $environment[0];

        $document = $searchService->get($environment, $template->getContentType(), $ouuid);

        try {
            $body = $twig->createTemplate($template->getBody());
        } catch (Error $e) {
            $logger->error('log.template.twig.error', [
                'template_id' => $template->getId(),
                'template_name' => $template->getName(),
                'error_message' => $e->getMessage(),
            ]);
            $body = $twig->createTemplate($translator->trans('log.template.twig.error', [
                '%template_id%' => $template->getId(),
                '%template_name%' => $template->getName(),
                '%error_message%' => $e->getMessage(),
            ], EMSCoreBundle::TRANS_DOMAIN));
        }

        if (RenderOptionType::PDF === $template->getRenderOption() && ($_download || !$template->getPreview())) {
            $output = $body->render([
                'environment' => $environment,
                'contentType' => $template->getContentType(),
                'object' => $document,
                'source' => $document->getSource(),
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
            $dompdf->stream($template->getFilename() ?? 'document.pdf', [
                'compress' => 1,
                'Attachment' => ($template->getDisposition() && 'attachment' === $template->getDisposition()) ? 1 : 0,
            ]);
            exit;
        }
        if ($_download || (0 === \strcmp($template->getRenderOption(), RenderOptionType::EXPORT) && !$template->getPreview())) {
            if (null != $template->getMimeType()) {
                \header('Content-Type: '.$template->getMimeType());
            }

            $filename = $ouuid;
            if (null != $template->getFilename()) {
                try {
                    $filename = $twig->createTemplate($template->getFilename());
                } catch (Error $e) {
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
                    'object' => $document,
                    'source' => $document->getSource(),
                ]);
                $filename = \preg_replace('~[\r\n]+~', '', $filename);
            }

            if (!empty($template->getDisposition())) {
                $attachment = ResponseHeaderBag::DISPOSITION_ATTACHMENT;
                if ('inline' == $template->getDisposition()) {
                    $attachment = ResponseHeaderBag::DISPOSITION_INLINE;
                }
                \header("Content-Disposition: $attachment; filename=".$filename.($template->getExtension() ? '.'.$template->getExtension() : ''));
            }
            if (null != $template->getAllowOrigin()) {
                \header('Access-Control-Allow-Origin: '.$template->getAllowOrigin());
                \header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, Accept-Language, If-None-Match, If-Modified-Since');
                \header('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS');
            }

            $output = $body->render([
                'environment' => $environment,
                'contentType' => $template->getContentType(),
                'object' => $document,
                'source' => $document->getSource(),
            ]);
            echo $output;

            exit;
        }

        return $this->render('@EMSCore/data/custom-view.html.twig', [
            'template' => $template,
            'object' => $document,
            'environment' => $environment,
            'contentType' => $template->getContentType(),
            'body' => $body,
        ]);
    }

    /**
     * @param string $environmentName
     * @param int    $templateId
     * @param string $ouuid
     *
     * @return Response
     *
     * @throws \Throwable
     * @Route("/data/custom-view-job/{environmentName}/{templateId}/{ouuid}", name="ems_job_custom_view", methods={"POST"})
     */
    public function customViewJobAction($environmentName, $templateId, $ouuid, LoggerInterface $logger, SearchService $searchService, Request $request, TwigEnvironment $twig, JobService $jobService)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var Template|null $template * */
        $template = $em->getRepository(Template::class)->find($templateId);
        /** @var Environment|null $env */
        $env = $em->getRepository(Environment::class)->findOneByName($environmentName);

        if (null === $template || null === $env) {
            throw new NotFoundHttpException();
        }

        $document = $searchService->get($env, $template->getContentType(), $ouuid);

        $success = false;
        try {
            $command = $twig->createTemplate($template->getBody())->render([
                'environment' => $env->getName(),
                'contentType' => $template->getContentType(),
                'object' => $document,
                'source' => $document->getSource(),
            ]);

            $user = $this->getUser();
            if (!$user instanceof UserInterface) {
                throw new \RuntimeException('Unexpected user object');
            }
            $job = $jobService->createCommand($user, $command);

            $success = true;
            $logger->notice('log.data.job.initialized', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $template->getContentType()->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                EmsFields::LOG_OUUID_FIELD => $ouuid,
                'template_id' => $template->getId(),
                'job_id' => $job->getId(),
                'template_name' => $template->getName(),
                'environment' => $env->getName(),
            ]);

            return $this->returnJsonResponse($request, true, [
                'jobId' => $job->getId(),
                'jobUrl' => $this->generateUrl('emsco_job_start', ['job' => $job->getId()], UrlGeneratorInterface::ABSOLUTE_PATH),
                'url' => $this->generateUrl('emsco_job_status', ['job' => $job->getId()], UrlGeneratorInterface::ABSOLUTE_PATH),
            ]);
        } catch (\Throwable $e) {
            $logger->error('log.data.job.initialize_failed', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $template->getContentType()->getName(),
                EmsFields::LOG_OUUID_FIELD => $ouuid,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                EmsFields::LOG_EXCEPTION_FIELD => $e,
            ]);
        }

        $response = $this->render('@EMSCore/ajax/notification.json.twig', [
            'success' => $success,
        ]);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * @param int $revisionId
     *
     * @return Response
     *
     * @throws LockedException
     * @throws PrivilegeException
     * @throws \Exception
     * @Route("/data/revision/{revisionId}.json", name="revision.ajaxupdate", defaults={"_format"="json"}, methods={"POST"})
     */
    public function ajaxUpdateAction($revisionId, Request $request, DataService $dataService, LoggerInterface $logger, PublishService $publishService)
    {
        $em = $this->getDoctrine()->getManager();
        $formErrors = [];

        /** @var RevisionRepository $repository */
        $repository = $em->getRepository('EMSCoreBundle:Revision');
        /** @var Revision|null $revision */
        $revision = $repository->find($revisionId);

        if (null === $revision) {
            throw new NotFoundHttpException('Revision not found');
        }

        if (!$revision->getDraft() || null !== $revision->getEndTime()) {
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

            $revision->setAutoSaveAt(new \DateTime());
            $revision->setAutoSaveBy($this->getUser()->getUsername());

            $em->persist($revision);
            $em->flush();

            $dataService->isValid($form, null, $objectArray);
            $dataService->propagateDataToComputedField($form->get('data'), $objectArray, $revision->getContentType(), $revision->getContentType()->getName(), $revision->getOuuid(), false, false);

            $session = $request->getSession();
            if ($session instanceof Session) {
                $session->getFlashBag()->set('warning', []);
            }

            $formErrors = $form->getErrors(true, true);

            if (0 === $formErrors->count() && $revision->getContentType()->isAutoPublish()) {
                $publishService->silentPublish($revision);
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
     * @return RedirectResponse|Response
     * @Route("/data/draft/finalize/{revision}", name="revision.finalize", methods={"POST"})
     */
    public function finalizeDraftAction(Revision $revision, LoggerInterface $logger, DataService $dataService)
    {
        $dataService->loadDataStructure($revision);
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

            $revision = $dataService->finalizeDraft($revision, $form);
            if (0 !== \count($form->getErrors())) {
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
        } catch (\Throwable $e) {
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
     * @Route("/data/duplicate-json/{contentType}/{ouuid}", name="emsco_data_duplicate_with_jsoncontent", methods={"POST"})
     */
    public function duplicateWithJsonContentAction(ContentType $contentType, string $ouuid, Request $request, DataService $dataService, LoggerInterface $logger): RedirectResponse
    {
        $content = $request->get('JSON_BODY', null);
        $jsonContent = \json_decode($content, true);
        $jsonContent = \array_merge($dataService->getNewestRevision($contentType->getName(), $ouuid)->getRawData(), $jsonContent);

        return $this->intNewDocumentFromArray($contentType, $dataService, $logger, $jsonContent);
    }

    /**
     * @Route("/data/add-json/{contentType}", name="emsco_data_add_from_jsoncontent", methods={"POST"})
     */
    public function addFromJsonContentAction(ContentType $contentType, Request $request, DataService $dataService, LoggerInterface $logger): RedirectResponse
    {
        $content = $request->get('JSON_BODY', null);
        $jsonContent = \json_decode($content, true);
        if (null === $jsonContent) {
            $logger->error('log.data.revision.add_from_json_error', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
            ]);

            return $this->redirectToRoute('data.root', [
                'name' => $contentType->getName(),
            ]);
        }

        return $this->intNewDocumentFromArray($contentType, $dataService, $logger, $jsonContent);
    }

    private function intNewDocumentFromArray(ContentType $contentType, DataService $dataService, LoggerInterface $logger, array $rawData): RedirectResponse
    {
        $dataService->hasCreateRights($contentType);

        try {
            $revision = $dataService->newDocument($contentType, null, $rawData);

            return $this->redirectToRoute('revision.edit', [
                'revisionId' => $revision->getId(),
            ]);
        } catch (\Throwable $e) {
            $logger->error('log.data.revision.init_document_from_array', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_CREATE,
                EmsFields::LOG_EXCEPTION_FIELD => $e,
                EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
            ]);

            return $this->redirectToRoute('data.root', [
                'name' => $contentType->getName(),
            ]);
        }
    }

    /**
     * @return RedirectResponse|Response
     *
     * @throws HasNotCircleException
     * @Route("/data/add/{contentType}", name="data.add")
     */
    public function addAction(ContentType $contentType, Request $request, DataService $dataService)
    {
        $dataService->hasCreateRights($contentType);

        $revision = new Revision();
        $form = $this->createFormBuilder($revision)
            ->add('ouuid', IconTextType::class, [
                'constraints' => [new Regex([
                    'pattern' => '/^[A-Za-z0-9_\.\-~]*$/',
                    'match' => true,
                    'message' => 'Ouuid has an unauthorized character.',
                    ]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Auto-generated if left empty',
                ],
                'required' => false,
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Create '.$contentType->getName().' draft',
                'attr' => [
                    'class' => 'btn btn-primary pull-right',
                ],
            ])
            ->getForm();

        $form->handleRequest($request);

        if (($form->isSubmitted() && $form->isValid()) || !$contentType->getAskForOuuid()) {
            /** @var Revision $revision */
            $revision = $form->getData();
            try {
                $revision = $dataService->newDocument($contentType, $revision->getOuuid());

                return $this->redirectToRoute('revision.edit', [
                    'revisionId' => $revision->getId(),
                ]);
            } catch (DuplicateOuuidException $e) {
                $form->get('ouuid')->addError(new FormError('Another '.$contentType->getName().' with this identifier already exists'));
            }
        }

        return $this->render('@EMSCore/data/add.html.twig', [
            'contentType' => $contentType,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @return RedirectResponse
     *
     * @throws ElasticmsException
     * @throws \Exception
     * @Route("/data/revisions/revert/{id}", name="revision.revert", methods={"POST"})
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
            'revisionId' => $revertedRevision->getId(),
        ]);
    }

    /**
     * @return RedirectResponse
     * @Route("/data/link/{key}", name="data.link")
     */
    public function linkDataAction(string $key, ContentTypeService $ctService)
    {
        $category = $type = $ouuid = null;
        $split = \explode(':', $key);

        if (3 === \count($split)) {
            $category = $split[0]; // object or asset
            $type = $split[1];
            $ouuid = $split[2];
        }

        if (null != $ouuid && null != $type) {
            /** @var EntityManager $em */
            $em = $this->getDoctrine()->getManager();

            /** @var RevisionRepository $repository */
            $repository = $em->getRepository('EMSCoreBundle:Revision');

            $contentType = $ctService->getByName($type);

            if (empty($contentType)) {
                throw new NotFoundHttpException('Content type '.$type.'not found');
            }

            /** @var Revision $revision */
            $revision = $repository->findByOuuidAndContentTypeAndEnvironment($contentType, $ouuid, $contentType->getEnvironment());

            if (!$revision) {
                throw new NotFoundHttpException('Impossible to find this item : '.$ouuid);
            }

            // For each type, we must perform a different redirect.
            if ('object' == $category) {
                return $this->redirectToRoute('data.revisions', [
                    'type' => $type,
                    'ouuid' => $ouuid,
                ]);
            }
            if ('asset' == $category) {
                if (empty($contentType->getAssetField()) && empty($revision->getRawData()[$contentType->getAssetField()])) {
                    throw new NotFoundHttpException('Asset field not found for '.$revision);
                }

                return $this->redirectToRoute('file.download', [
                    'sha1' => $revision->getRawData()[$contentType->getAssetField()]['sha1'],
                    'type' => $revision->getRawData()[$contentType->getAssetField()]['mimetype'],
                    'name' => $revision->getRawData()[$contentType->getAssetField()]['filename'],
                ]);
            }
        }
        throw new NotFoundHttpException('Impossible to find this item : '.$key);
    }

    private function loadAutoSavedVersion(Revision $revision, LoggerInterface $logger): void
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

    /**
     * @param array<mixed> $input
     */
    private function reorderCollection(array &$input): void
    {
        if (empty($input)) {
            return;
        }
        $keys = \array_keys($input);
        if (\is_int($keys[0])) {
            \sort($keys);
            $temp = [];
            $loop0 = 0;
            foreach ($input as $item) {
                $temp[$keys[$loop0]] = $item;
                ++$loop0;
            }
            $input = $temp;
        }
        foreach ($input as &$elem) {
            if (\is_array($elem)) {
                $this->reorderCollection($elem);
            }
        }
    }
}
