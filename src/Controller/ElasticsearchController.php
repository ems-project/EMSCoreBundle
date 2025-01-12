<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Controller;

use Elasticsearch\Common\Exceptions\ElasticsearchException;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use EMS\CommonBundle\Common\EMSLink;
use EMS\CommonBundle\Elasticsearch\Document\EMSSource;
use EMS\CommonBundle\Elasticsearch\Response\Response as CommonResponse;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Search\Search as CommonSearch;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Commands;
use EMS\CoreBundle\Core\Dashboard\DashboardManager;
use EMS\CoreBundle\Core\Document\DataLinks;
use EMS\CoreBundle\Entity\ContentType;
use EMS\CoreBundle\Entity\Dashboard;
use EMS\CoreBundle\Entity\Form\ExportDocuments;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\Form\SearchFilter;
use EMS\CoreBundle\Entity\UserInterface;
use EMS\CoreBundle\Form\Field\IconTextType;
use EMS\CoreBundle\Form\Field\SubmitEmsType;
use EMS\CoreBundle\Form\Form\ExportDocumentsType;
use EMS\CoreBundle\Form\Form\SearchFormType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Repository\SearchRepository;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\AggregateOptionService;
use EMS\CoreBundle\Service\AssetExtractorService;
use EMS\CoreBundle\Service\ContentTypeService;
use EMS\CoreBundle\Service\DataService;
use EMS\CoreBundle\Service\EnvironmentService;
use EMS\CoreBundle\Service\IndexService;
use EMS\CoreBundle\Service\JobService;
use EMS\CoreBundle\Service\Revision\RevisionService;
use EMS\CoreBundle\Service\SearchService;
use EMS\CoreBundle\Service\SortOptionService;
use EMS\Helpers\Standard\Json;
use EMS\Helpers\Standard\Type;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\ClickableInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ElasticsearchController extends AbstractController
{
    /**
     * @param string[] $elasticsearchCluster
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly IndexService $indexService,
        private readonly ElasticaService $elasticaService,
        private readonly DataService $dataService,
        private readonly AssetExtractorService $assetExtractorService,
        private readonly EnvironmentService $environmentService,
        private readonly ContentTypeService $contentTypeService,
        private readonly RevisionService $revisionService,
        private readonly SearchService $searchService,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly JobService $jobService,
        private readonly AggregateOptionService $aggregateOptionService,
        private readonly SortOptionService $sortOptionService,
        private readonly DashboardManager $dashboardManager,
        private readonly ContentTypeRepository $contentTypeRepository,
        private readonly SearchRepository $searchRepository,
        private readonly EnvironmentRepository $environmentRepository,
        private readonly int $pagingSize,
        private readonly ?string $healthCheckAllowOrigin,
        private readonly array $elasticsearchCluster,
        private readonly string $templateNamespace)
    {
    }

    public function addAlias(string $name, Request $request): Response
    {
        $form = $this->createFormBuilder([])->add('name', IconTextType::class, [
            'icon' => 'fa fa-key',
            'required' => true,
        ])->add('save', SubmitEmsType::class, [
            'label' => 'Add',
            'icon' => 'fa fa-plus',
            'attr' => [
                'class' => 'btn btn-primary pull-right',
            ],
        ])->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $aliasName = $form->get('name')->getData();
            $this->indexService->updateAlias($aliasName, [], [$name]);
            $this->logger->notice('log.elasticsearch.alias_added', [
                'alias_name' => $aliasName,
                'index_name' => $name,
            ]);

            return $this->redirectToRoute(Routes::ADMIN_ENVIRONMENT_INDEX);
        }

        return $this->render("@$this->templateNamespace/elasticsearch/add-alias.html.twig", [
            'form' => $form->createView(),
            'name' => $name,
        ]);
    }

    public function healthCheck(string $_format): Response
    {
        try {
            $health = $this->elasticaService->getClusterHealth();

            $response = $this->render("@$this->templateNamespace/elasticsearch/status.$_format.twig", [
                'status' => $health,
                'globalStatus' => $health['status'] ?? 'red',
            ]);

            $allowOrigin = $this->healthCheckAllowOrigin;
            if (\is_string($allowOrigin) && \strlen($allowOrigin) > 0) {
                $response->headers->set('Access-Control-Allow-Origin', $allowOrigin);
            }

            return $response;
        } catch (\Exception $e) {
            throw new ServiceUnavailableHttpException('Due to '.$e->getMessage());
        }
    }

    public function status(string $_format): Response
    {
        try {
            $status = $this->elasticaService->getClusterHealth();
            $certificateInformation = $this->dataService->getCertificateInfo();

            $globalStatus = 'green';
            try {
                $tika = $this->assetExtractorService->hello();
            } catch (\Exception $e) {
                $globalStatus = 'yellow';
                $tika = [
                    'code' => 500,
                    'content' => $e->getMessage(),
                ];
            }

            if ('html' === $_format && 'green' !== $status['status']) {
                $globalStatus = $status['status'];
                if ('red' === $status['status']) {
                    $this->logger->error('log.elasticsearch.cluster_red', [
                        'color_status' => $status['status'],
                    ]);
                } else {
                    $this->logger->warning('log.elasticsearch.cluster_yellow', [
                        'color_status' => $status['status'],
                    ]);
                }
            }

            return $this->render("@$this->templateNamespace/elasticsearch/status.$_format.twig", [
                'status' => $status,
                'certificate' => $certificateInformation,
                'tika' => $tika,
                'globalStatus' => $globalStatus,
                'info' => $this->elasticaService->getClusterInfo(),
                'specifiedVersion' => $this->elasticaService->getVersion(),
            ]);
        } catch (NoNodesAvailableException) {
            return $this->render("@$this->templateNamespace/elasticsearch/no-nodes-available.$_format.twig", [
                'cluster' => $this->elasticsearchCluster,
            ]);
        }
    }

    public function indexSearch(): Response
    {
        return $this->render("@$this->templateNamespace/elasticsearch/index.html.twig", [
            'data' => $this->searchService->getAll(),
        ]);
    }

    /**
     * @param int $id
     */
    public function deleteSearch($id): Response
    {
        $search = $this->searchRepository->find($id);
        if (null === $search) {
            throw $this->createNotFoundException('Preset saved search not found');
        }
        $this->searchRepository->remove($search);

        return $this->redirectToRoute('elasticsearch.search');
    }

    public function quickSearch(Request $request): Response
    {
        $dashboard = $this->dashboardManager->getDefinition(Dashboard::DEFINITION_QUICK_SEARCH);
        if (null !== $dashboard) {
            return $this->redirectToRoute(Routes::DASHBOARD, ['name' => $dashboard->getName(), 'q' => $query = $request->query->get('q', '')]);
        }
        $query = $request->query->get('q');
        $search = $this->searchRepository->findOneBy([
            'default' => true,
        ]);
        if ($search instanceof Search) {
            /** @var SearchFilter $filter */
            foreach ($search->getFilters() as &$filter) {
                if (empty($filter->getPattern())) {
                    $filter->setPattern($query);
                }
            }
        } else {
            $search = new Search();
            $search->setEnvironments($this->environmentService->getEnvironmentNames());
            if (false !== $query) {
                $search->getFirstFilter()->setPattern($query)->setBooleanClause('must');
            }
        }

        return $this->forward('EMS\CoreBundle\Controller\ElasticsearchController::search', [
            'query' => null,
        ], [
            'search_form' => $search->jsonSerialize(),
        ]);
    }

    public function setDefaultSearch(int $id, ?string $contentType): Response
    {
        if (null !== $contentType) {
            $contentType = $this->contentTypeService->giveByName($contentType);
            $searchs = $this->searchRepository->findBy([
                'contentType' => $contentType->getId(),
            ]);
            /** @var Search $search */
            foreach ($searchs as $search) {
                $search->setContentType(null);
                $this->searchRepository->save($search);
            }

            $search = $this->searchRepository->find($id);
            if ($search instanceof Search) {
                $search->setContentType($contentType);
                $this->searchRepository->save($search);
                $this->logger->notice('log.elasticsearch.default_search_for_content_type', [
                    EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                ]);
            }
        } else {
            $searchs = $this->searchRepository->findBy([
                'default' => true,
            ]);
            /** @var Search $search */
            foreach ($searchs as $search) {
                $search->setDefault(false);
                $this->searchRepository->save($search);
            }
            $search = $this->searchRepository->find($id);

            if ($search instanceof Search) {
                $search->setDefault(true);
                $this->searchRepository->save($search);
                $this->logger->notice('log.elasticsearch.default_search');
            }
        }

        return $this->redirectToRoute('elasticsearch.search', ['searchId' => $id]);
    }

    /** @deprecated */
    public function deprecatedSearchApi(Request $request, DataLinks $dataLinks): void
    {
        @\trigger_error('QuerySearch not defined, you should refer to one', E_USER_DEPRECATED);
        $environments = Type::string($request->query->get('environment', ''));
        $searchId = $dataLinks->getSearchId();
        $category = $request->query->get('category');
        $assetName = $request->query->get('asset_name');
        $circleOnly = $request->query->get('circle');
        $dataLink = $request->query->get('dataLink');

        if (\is_string($dataLink)) {
            $emsLink = EMSLink::fromText($dataLink);
            $contentType = $this->contentTypeService->giveByName($emsLink->getContentType());
            $document = $this->searchService->getDocument($contentType, $emsLink->getOuuid());

            $dataLinks->addContentTypes($contentType);
            $dataLinks->addDocument(document: $document, displayLabel: $this->revisionService->display($document));

            return;
        }

        $contentTypes = $dataLinks->getContentTypeNames();

        $search = null;
        if ($searchId) {
            $search = $this->searchRepository->findOneBy(['id' => $searchId]);
        }

        if (!$search instanceof Search) {
            $search = $this->searchService->getDefaultSearch($contentTypes);
        }

        $searchContentTypes = $search->getContentTypes();
        foreach ($searchContentTypes as $searchContentType) {
            $dataLinks->addContentTypes($this->contentTypeService->giveByName($searchContentType));
        }

        if ($assetName) {
            $allContentTypes = $this->contentTypeService->getAll();
            // For search only in contentType with Asset field == $assetName.
            $contentTypes = [];
            foreach ($allContentTypes as $contentType) {
                if ($contentType->hasAssetField()) {
                    $contentTypes[] = $contentType->getName();
                }
            }
        }

        if (\count($contentTypes) > 0) {
            $search->setContentTypes($contentTypes);
        }

        if (!empty($environments) && null === $searchId) {
            $search->setEnvironments(\explode(',', $environments));
        }

        $search->setSearchPattern($dataLinks->getPattern(), true);
        $commonSearch = $this->searchService->generateSearch($search);

        if ($circleOnly && !$this->authorizationChecker->isGranted('ROLE_USER_MANAGEMENT')) {
            /** @var UserInterface $user */
            $user = $this->getUser();
            $circles = $user->getCircles();

            $ouuids = [];
            foreach ($circles as $circle) {
                \preg_match('/(?P<type>\w+):(?P<ouuid>\w+)/', (string) $circle, $matches);
                if (isset($matches['ouuid'])) {
                    $ouuids[] = $matches['ouuid'];
                }
            }
            $query = $commonSearch->getQuery();
            $boolQuery = $this->elasticaService->getBoolQuery();
            if (!$query instanceof $boolQuery) {
                if (null !== $query) {
                    $boolQuery->addMust($query);
                }
                $query = $boolQuery;
            }
            $query->addMust($this->elasticaService->getTermsQuery('_id', $ouuids));
            $commonSearch = new CommonSearch($commonSearch->getIndices(), $query);
        }

        if (null !== $category && 1 === \count($contentTypes)) {
            $contentType = $this->contentTypeService->getByName(\array_values($contentTypes)[0]);
            if (false !== $contentType) {
                if ($contentType->hasCategoryField()) {
                    $categoryField = $contentType->giveCategoryField();
                    $boolQuery = $this->elasticaService->getBoolQuery();
                    $query = $commonSearch->getQuery();
                    if (!$query instanceof $boolQuery) {
                        if (null !== $query) {
                            $boolQuery->addMust($query);
                        }
                        $query = $boolQuery;
                    }
                    $query->addMust($this->elasticaService->getTermsQuery($categoryField, [$category]));
                    $commonSearch = new CommonSearch($commonSearch->getIndices(), $query);
                }
            }
        }

        $commonSearch->setFrom($dataLinks->getFrom());
        $commonSearch->setSize($dataLinks->getSize());
        $response = CommonResponse::fromResultSet($this->elasticaService->search($commonSearch));

        $dataLinks->setTotal($response->getTotal());
        foreach ($response->getDocuments() as $document) {
            $dataLinks->addDocument(document: $document, displayLabel: $this->revisionService->display($document));
        }
    }

    public function export(Request $request, ContentType $contentType): Response
    {
        $exportDocuments = new ExportDocuments($contentType, $this->generateUrl('emsco_search_export', ['contentType' => $contentType]), '{}');
        $form = $this->createForm(ExportDocumentsType::class, $exportDocuments);
        $form->handleRequest($request);

        /** @var ExportDocuments */
        $exportDocuments = $form->getData();
        $command = \sprintf(
            "%s %s %s '%s'%s --environment=%s --baseUrl=%s",
            Commands::CONTENT_TYPE_EXPORT,
            $contentType->getName(),
            $exportDocuments->getFormat(),
            $exportDocuments->getQuery(),
            $exportDocuments->isWithBusinessKey() ? ' --withBusinessId' : '',
            $exportDocuments->getEnvironment(),
            '//'.$request->getHttpHost()
        );
        $user = $this->getUser();
        if (!$user instanceof UserInterface) {
            throw new \RuntimeException('Unexpected user object');
        }

        $job = $this->jobService->createCommand($user, $command);

        return $this->redirectToRoute('job.status', [
            'job' => $job->getId(),
        ]);
    }

    public function search(Request $request): Response
    {
        try {
            $search = new Search();
            $search->setEnvironments($this->environmentService->getEnvironmentNames());

            if ('POST' == $request->getMethod()) {
                $request->request->set('search_form', $request->query->all('search_form'));

                $form = $this->createForm(SearchFormType::class, $search);

                $form->handleRequest($request);
                /** @var Search $search */
                $search = $form->getData();
                $search->setName($request->request->all('form')['name']);

                $user = $this->getUser();
                if (!$user instanceof UserInterface) {
                    throw new \RuntimeException('User not found');
                }
                $search->setUser($user->getUsername());

                /** @var SearchFilter $filter */
                foreach ($search->getFilters() as $filter) {
                    $filter->setSearch($search);
                }
                $this->searchRepository->save($search);

                return $this->redirectToRoute('elasticsearch.search', [
                    'searchId' => $search->getId(),
                ]);
            }

            $page = $request->query->getInt('page', 1);

            // Use search from a saved form
            $searchId = $request->query->get('searchId');
            if (null != $searchId) {
                $search = $this->searchRepository->find($request->query->get('searchId'));
                if (!$search instanceof Search) {
                    $this->createNotFoundException('Preset search not found');
                }
            }

            $form = $this->createForm(SearchFormType::class, $search, [
                'method' => 'GET',
                'action' => $this->generateUrl('ems_search'),
                'savedSearch' => $searchId,
            ]);

            $form->handleRequest($request);

            $openSearchForm = false;
            $searchButton = $form->get('search');
            if ($searchButton instanceof ClickableInterface) {
                $openSearchForm = $searchButton->isClicked();
            }

            // Form treatment after the "Save" button has been pressed (= ask for a name to save the search preset)
            if ($form->isSubmitted() && $form->isValid() && \array_key_exists('save', $request->query->all('search_form'))) {
                $form = $this->createFormBuilder($search)
                    ->add('name', TextType::class)
                    ->add('save_search', SubmitEmsType::class, [
                        'label' => 'Save',
                        'attr' => [
                            'class' => 'btn btn-primary pull-right',
                        ],
                        'icon' => 'fa fa-save',
                    ])
                    ->getForm();

                return $this->render("@$this->templateNamespace/elasticsearch/save-search.html.twig", [
                    'form' => $form->createView(),
                ]);
            } elseif ($form->isSubmitted() && $form->isValid() && \array_key_exists('delete', $request->query->all('search_form'))) {
                // Form treatment after the "Delete" button has been pressed (to delete a previous saved search preset)

                $this->logger->notice('log.elasticsearch.search_deleted', [
                ]);
            }

            /** @var Search $search */
            $search = $form->getData();
            $types = $this->contentTypeRepository->findAllAsAssociativeArray();
            $environments = $this->environmentRepository->findAllAsAssociativeArray('alias');

            $esSearch = $this->searchService->generateSearch($search);
            $esSearch->setFrom(($page - 1) * $this->pagingSize);
            $esSearch->setSize(Type::integer($this->pagingSize));

            $esSearch->addTermsAggregation(AggregateOptionService::CONTENT_TYPES_AGGREGATION, EMSSource::FIELD_CONTENT_TYPE, 15);
            $esSearch->addTermsAggregation(AggregateOptionService::INDEXES_AGGREGATION, '_index', 15);
            $esSearch->addAggregations($this->aggregateOptionService->getAllAggregations());

            try {
                $response = CommonResponse::fromResultSet($this->elasticaService->search($esSearch));
                if ($response->getTotal() >= 50000) {
                    $this->logger->warning('log.elasticsearch.paging_limit_exceeded', [
                        'total' => $response->getTotal(),
                        'paging' => '50.000',
                    ]);
                    $lastPage = \ceil(50000 / $this->pagingSize);
                } else {
                    $lastPage = \ceil($response->getTotal() / $this->pagingSize);
                }
            } catch (ElasticsearchException $e) {
                $this->logger->warning('log.error', [
                    EmsFields::LOG_ERROR_MESSAGE_FIELD => $e->getMessage(),
                    EmsFields::LOG_EXCEPTION_FIELD => $e,
                ]);
                $response = null;
                $lastPage = 0;
            }

            if (null !== $response && !$response->isAccurate()) {
                $this->logger->warning('log.elasticsearch.search_not_accurate', [
                    'total' => $response->getTotal(),
                ]);
            }

            $currentFilters = $request->query;
            $currentFilters->remove('search_form[_token]');

            // Form treatment after the "Export results" button has been pressed (= ask for a "content type" <-> "template" mapping)
            if (null !== $response && $form->isSubmitted() && $form->isValid() && \array_key_exists('exportResults', $request->query->all('search_form'))) {
                $exportForms = [];
                $contentTypes = $this->getAllContentType($response);
                foreach ($contentTypes as $name) {
                    $contentType = $types[$name];

                    $exportForm = $this->createForm(ExportDocumentsType::class, new ExportDocuments(
                        $contentType,
                        $this->generateUrl('emsco_search_export', ['contentType' => $contentType->getId()]),
                        Json::encode($this->searchService->generateSearchBody($search))
                    ));

                    $exportForms[] = $exportForm->createView();
                }

                return $this->render("@$this->templateNamespace/elasticsearch/export-search.html.twig", [
                    'exportForms' => $exportForms,
                ]);
            }

            $mapIndex = [];
            if (null !== $response) {
                $indexes = $response->getAggregation(AggregateOptionService::INDEXES_AGGREGATION);
                if (null !== $indexes) {
                    foreach ($indexes->getBuckets() as $bucket) {
                        $indexName = $bucket->getKey();
                        if (null === $indexName) {
                            continue;
                        }
                        $aliases = $this->elasticaService->getAliasesFromIndex($indexName);
                        foreach ($aliases as $alias) {
                            if (isset($environments[$alias])) {
                                $mapIndex[$indexName] = $environments[$alias];
                                break;
                            }
                        }
                    }
                }
            }

            return $this->render("@$this->templateNamespace/elasticsearch/search.html.twig", [
                'response' => $response ?? null,
                'lastPage' => $lastPage,
                'paginationPath' => 'elasticsearch.search',
                'types' => $types,
                'indexes' => $mapIndex,
                'form' => $form->createView(),
                'page' => $page,
                'searchId' => $searchId,
                'currentFilters' => $request->query,
                'body' => $this->searchService->generateSearchBody($search),
                'openSearchForm' => $openSearchForm,
                'search' => $search,
                'sortOptions' => $this->sortOptionService->getAll(),
                'aggregateOptions' => $this->aggregateOptionService->getAll(),
            ]);
        } catch (NoNodesAvailableException) {
            return $this->redirectToRoute('elasticsearch.status');
        }
    }

    /**
     * @return string[]
     */
    private function getAllContentType(CommonResponse $response): array
    {
        $aggregation = $response->getAggregation(AggregateOptionService::CONTENT_TYPES_AGGREGATION);
        if (null === $aggregation) {
            return [];
        }

        return $aggregation->getKeys();
    }
}
