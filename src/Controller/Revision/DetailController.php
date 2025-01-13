<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller\Revision;

use EMS\CommonBundle\Elasticsearch\Response\Response as CommonResponse;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Core\DataTable\DataTableFactory;
use EMS\CoreBundle\Core\Log\LogRevisionContext;
use EMS\CoreBundle\DataTable\Type\Revision\RevisionAuditDataTableType;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\Form\SearchFilter;
use EMS\CoreBundle\Form\Form\RevisionType;
use EMS\CoreBundle\Form\Form\TableType;
use EMS\CoreBundle\Repository\RevisionRepository;
use EMS\CoreBundle\Roles;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\CoreBundle\Service\SearchService;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DetailController extends AbstractController
{
    public function __construct(
        private readonly ContentTypeService $contentTypeService,
        private readonly DataService $dataService,
        private readonly RevisionService $revisionService,
        private readonly RevisionRepository $revisionRepository,
        private readonly ElasticaService $elasticaService,
        private readonly SearchService $searchService,
        private readonly DataTableFactory $dataTableFactory,
        private readonly LoggerInterface $logger,
        private readonly string $templateNamespace
    ) {
    }

    public function detailRevision(Request $request, string $type, string $ouuid, int $revisionId, int $compareId): Response
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

        $revision = $this->revisionService->findByIdOrOuuid($contentType, $revisionId, $ouuid);

        if (null === $revision && $contentType->hasVersionTags() && Uuid::isValid($ouuid)) {
            // using version ouuid as ouuid should redirect to latest
            $searchLatestVersion = $this->revisionRepository->findLatestVersion($contentType, $ouuid);
            if ($searchLatestVersion && $searchLatestVersion->getOuuid() !== $ouuid) {
                return $this->redirectToRoute('emsco_view_revisions', [
                    'type' => $contentType->getName(),
                    'ouuid' => $searchLatestVersion->getOuuid(),
                ]);
            }
        }

        if (null === $revision) {
            throw new NotFoundHttpException('Revision not found');
        }

        $compareData = (0 !== $compareId ? $this->revisionService->compare($revision, $compareId) : null);

        $this->dataService->testIntegrityInIndexes($revision);

        if (null != $revision->getAutoSave()) {
            $revision->setRawData($revision->getAutoSave());
            $this->logger->notice('log.data.revision.load_from_auto_save', LogRevisionContext::read($revision));
        }

        $page = $request->query->getInt('page', 1);

        $revisionsSummary = $this->revisionRepository->getAllRevisionsSummary($ouuid, $contentType, $page);
        $lastPage = $this->revisionRepository->revisionsLastPage($ouuid, $contentType);
        $counter = $this->revisionRepository->countRevisions($ouuid, $contentType);
        $firstElemOfPage = $this->revisionRepository->firstElemOfPage($page);

        $form = $this->createForm(RevisionType::class, $revision, ['raw_data' => $revision->getRawData()]);

        $objectArray = $form->getData()->getRawData();

        $dataFields = $this->dataService->getDataFieldsStructure($form->get('data'));

        $searchForm = new Search();
        $searchForm->setContentTypes($this->contentTypeService->getAllNames());
        $searchForm->setEnvironments($this->contentTypeService->getAllDefaultEnvironmentNames());
        $searchForm->setSortBy('_uid');
        $searchForm->setSortOrder('asc');

        $filter = $searchForm->getFirstFilter();
        $filter->setBooleanClause('should');
        $filter->setField($contentType->getRefererFieldName());
        $filter->setPattern(\sprintf('%s:%s', $type, $ouuid));
        $filter->setOperator('term');

        $filter = new SearchFilter();
        $filter->setBooleanClause('should');
        $filter->setField($contentType->getRefererFieldName());
        $filter->setPattern(\sprintf('%s\\:%s', $type, $ouuid));
        $filter->setOperator('query_and');
        $searchForm->addFilter($filter);

        $filter = new SearchFilter();
        $filter->setBooleanClause('should');
        $filter->setField($contentType->getRefererFieldName());
        $filter->setPattern(\sprintf('object\\:%s\\:%s', $type, $ouuid));
        $filter->setOperator('query_and');
        $searchForm->addFilter($filter);

        if (null !== $versionOuuid = $revision->getVersionUuid()) {
            $filterVersion = new SearchFilter();
            $filterVersion->setBooleanClause('should');
            $filterVersion->setField($contentType->getRefererFieldName());
            $filterVersion->setPattern(\sprintf('%s\\:%s', $type, $versionOuuid));
            $filterVersion->setOperator('query_and');
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

        if ($this->isGranted(Roles::ROLE_AUDITOR) || $this->isGranted(Roles::ROLE_ADMIN)) {
            $auditTable = $this->dataTableFactory->create(RevisionAuditDataTableType::class, [
                'revision_id' => $revision->getId(),
            ]);

            $auditTableForm = $this->createForm(TableType::class, $auditTable);
            $auditTableForm->handleRequest($request);
        }

        return $this->render("@$this->templateNamespace/data/revisions-data.html.twig", [
            'revision' => $revision,
            'revisionsSummary' => $revisionsSummary,
            'latestVersion' => $latestVersion ?? null,
            'object' => $revision->getObject($objectArray),
            'referrerResponse' => $referrerResponse,
            'page' => $page,
            'lastPage' => $lastPage,
            'counter' => $counter,
            'firstElemOfPage' => $firstElemOfPage,
            'dataFields' => $dataFields,
            'compareData' => $compareData,
            'compareId' => $compareId,
            'referrersForm' => $searchForm->jsonSerialize(),
            'auditCount' => isset($auditTable) ? $auditTable->count() : false,
            'auditTable' => isset($auditTableForm) ? $auditTableForm->createView() : null,
        ]);
    }
}
