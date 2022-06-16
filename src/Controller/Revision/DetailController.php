<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CommonBundle\Elasticsearch\Response\Response as CommonResponse;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\Form\SearchFilter;
use EMS\CoreBundle\Entity\Revision;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\SearchService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DetailController extends AbstractController
{
    private ContentTypeService $contentTypeService;
    private EnvironmentRepository $environmentRepository;
    private DataService $dataService;
    private RevisionRepository $revisionRepository;
    private ElasticaService $elasticaService;
    private SearchService $searchService;
    private LoggerInterface $logger;

    public function __construct(
        ContentTypeService $contentTypeService,
        EnvironmentRepository $environmentRepository,
        DataService $dataService,
        RevisionRepository $revisionRepository,
        ElasticaService $elasticaService,
        SearchService $searchService,
        LoggerInterface $logger
    ) {
        $this->contentTypeService = $contentTypeService;
        $this->environmentRepository = $environmentRepository;
        $this->dataService = $dataService;
        $this->revisionRepository = $revisionRepository;
        $this->elasticaService = $elasticaService;
        $this->searchService = $searchService;
        $this->logger = $logger;
    }

    public function detailRevision(string $type, string $ouuid, int $revisionId, int $compareId, Request $request): Response
    {
        $contentType = $this->contentTypeService->giveByName($type);
        $defaultEnvironment = $contentType->giveEnvironment();

        if (!$defaultEnvironment->getManaged()) {
            return $this->redirectToRoute('data.view', [
                'environmentName' => $defaultEnvironment->getName(),
                'type' => $type,
                'ouuid' => $ouuid,
            ]);
        }

        if (0 === $revisionId) {
            $revision = $this->revisionRepository->findOneBy([
                'endTime' => null,
                'ouuid' => $ouuid,
                'deleted' => false,
                'contentType' => $contentType,
            ]);
        } else {
            $revision = $this->revisionRepository->findOneById($revisionId);
        }

        if (!$revision instanceof Revision && $contentType->hasVersionTags()) {
            //using version ouuid as ouuid should redirect to latest
            $searchLatestVersion = $this->revisionRepository->findLatestVersion($contentType, $ouuid);
            if ($searchLatestVersion && $searchLatestVersion->getOuuid() !== $ouuid) {
                return $this->redirectToRoute('emsco_view_revisions', [
                    'type' => $contentType->getName(),
                    'ouuid' => $searchLatestVersion->getOuuid(),
                ]);
            }
        }

        if (!$revision instanceof Revision) {
            throw new \RuntimeException('Unexpected revision object');
        }

        $compareData = false;
        if (0 !== $compareId) {
            try {
                $compareRevision = $this->revisionRepository->findOneById($compareId);
                $compareData = $compareRevision->getRawData();
                if ($revision->giveContentType() === $compareRevision->giveContentType() && $revision->getOuuid() == $compareRevision->getOuuid()) {
                    if ($compareRevision->getCreated() <= $revision->getCreated()) {
                        $this->logger->notice('log.data.revision.compare', [
                            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                            'compare_revision_id' => $compareRevision->getId(),
                        ]);
                    } else {
                        $this->logger->warning('log.data.revision.compare_more_recent', [
                            EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                            EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                            EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                            'compare_revision_id' => $compareRevision->getId(),
                        ]);
                    }
                } else {
                    $this->logger->notice('log.data.document.compare', [
                        EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                        EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                        EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                        'compare_contenttype' => $compareRevision->giveContentType()->getName(),
                        'compare_ouuid' => $compareRevision->getOuuid(),
                        'compare_revision_id' => $compareRevision->getId(),
                    ]);
                }
            } catch (\Throwable $e) {
                $this->logger->warning('log.data.revision.compare_revision_not_found', [
                    EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                    EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                    EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
                    'compare_revision_id' => $compareId,
                ]);
            }
        }

        if ($revision->getOuuid() != $ouuid || $revision->getContentType() !== $contentType || $revision->getDeleted()) {
            throw new NotFoundHttpException('Revision not found');
        }

        $this->dataService->testIntegrityInIndexes($revision);

        $this->loadAutoSavedVersion($revision);

        $page = $request->query->get('page', 1);

        $revisionsSummary = $this->revisionRepository->getAllRevisionsSummary($ouuid, $contentType, $page);
        $lastPage = $this->revisionRepository->revisionsLastPage($ouuid, $contentType);
        $counter = $this->revisionRepository->countRevisions($ouuid, $contentType);
        $firstElemOfPage = $this->revisionRepository->firstElemOfPage($page);

        $availableEnv = $this->environmentRepository->findAvailableEnvironements(
            $revision->giveContentType()->giveEnvironment()
        );

        $form = $this->createForm(RevisionType::class, $revision, ['raw_data' => $revision->getRawData()]);

        $objectArray = $form->getData()->getRawData();

        $dataFields = $this->dataService->getDataFieldsStructure($form->get('data'));

        $searchForm = new Search();
        $searchForm->setContentTypes($this->contentTypeService->getAllNames());
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

        if (null !== $versionOuuid = $revision->getVersionUuid()) {
            $filterVersion = new SearchFilter();
            $filterVersion->setBooleanClause('should');
            $filterVersion->setField($contentType->getRefererFieldName());
            $filterVersion->setPattern(\sprintf('"%s:%s"', $type, $versionOuuid));
            $filterVersion->setOperator('match_and');
            $searchForm->addFilter($filterVersion);
        }

        $searchForm->setMinimumShouldMatch(1);
        $esSearch = $this->searchService->generateSearch($searchForm);
        $esSearch->setSize(100);
        $esSearch->setSources([]);

        $referrerResultSet = $this->elasticaService->search($esSearch);
        $referrerResponse = CommonResponse::fromResultSet($referrerResultSet);

        if ($contentType->hasVersionTags()
            && (null !== $versionOuuid = $revision->getVersionUuid())
            && null !== $revision->getVersionDate('to')) {
            $latestVersion = $this->revisionRepository->findLatestVersion($contentType, $versionOuuid->toString());
        }

        return $this->render('@EMSCore/data/revisions-data.html.twig', [
            'revision' => $revision,
            'revisionsSummary' => $revisionsSummary,
            'latestVersion' => $latestVersion ?? null,
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

    private function loadAutoSavedVersion(Revision $revision): void
    {
        if (null != $revision->getAutoSave()) {
            $revision->setRawData($revision->getAutoSave());
            $this->logger->notice('log.data.revision.load_from_auto_save', [
                EmsFields::LOG_CONTENTTYPE_FIELD => $revision->giveContentType()->getName(),
                EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_READ,
                EmsFields::LOG_OUUID_FIELD => $revision->getOuuid(),
                EmsFields::LOG_REVISION_ID_FIELD => $revision->getId(),
            ]);
        }
    }
}
