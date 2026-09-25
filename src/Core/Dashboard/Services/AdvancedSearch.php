<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Dashboard\Services;

use EMS\CommonBundle\Contracts\Log\LocalizedLoggerInterface;
use EMS\CommonBundle\Elasticsearch\Aggregation\ElasticaAggregation;
use EMS\CommonBundle\Elasticsearch\Document\EMSSource;
use EMS\CommonBundle\Elasticsearch\Response\Response as CommonResponse;
use EMS\CommonBundle\Helper\EmsFields;
use EMS\CommonBundle\Search\Search as CommonSearch;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\CommonBundle\Storage\StorageManager;
use EMS\CoreBundle\Core\Dashboard\DashboardOptions;
use EMS\CoreBundle\Core\UI\Page\Navigation;
use EMS\CoreBundle\Entity\Dashboard;
use EMS\CoreBundle\Entity\Form\ExportDocuments;
use EMS\CoreBundle\Entity\Form\Search;
use EMS\CoreBundle\Entity\Form\SearchFilter;
use EMS\CoreBundle\Form\Form\ExportDocumentsType;
use EMS\CoreBundle\Form\Form\SearchFormType;
use EMS\CoreBundle\Repository\ContentTypeRepository;
use EMS\CoreBundle\Repository\EnvironmentRepository;
use EMS\CoreBundle\Routes;
use EMS\CoreBundle\Service\SearchService;
use EMS\Helpers\Standard\Json;
use EMS\Helpers\Standard\Type;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

use function Symfony\Component\Translation\t;

class AdvancedSearch implements DashboardInterface
{
    final public const string CONTENT_TYPES_AGGREGATION = 'types';
    final public const string INDEXES_AGGREGATION = 'indexes';

    public function __construct(
        private readonly LocalizedLoggerInterface $logger,
        private readonly Environment $twig,
        private RequestStack $requestStack,
        private readonly FormFactory $formFactory,
        private readonly RouterInterface $router,
        private readonly StorageManager $storageManager,
        private readonly SearchService $searchService,
        private readonly ElasticaService $elasticaService,
        private readonly ContentTypeRepository $contentTypeRepository,
        private readonly EnvironmentRepository $environmentRepository,
        private readonly int $pagingSize,
        private readonly string $templateNamespace
    ) {
    }

    public function getResponse(Dashboard $dashboard, Navigation $breadcrumb): Response
    {
        $request = $this->requestStack->getMainRequest();
        if (null === $request) {
            throw new \RuntimeException('Request must be set');
        }

        if (Request::METHOD_POST === $request->getMethod() && isset(Type::array($request->request->all()['search_form'])['exportResults'])) {
            return $this->exportResult($request, $dashboard);
        }
        $uid = $request->query->get('uid');
        $options = $dashboard->getOptions();
        $isQuickSearch = Dashboard::DEFINITION_QUICK_SEARCH === $dashboard->getDefinition();
        $isLanding = Dashboard::DEFINITION_LANDING_PAGE === $dashboard->getDefinition();
        $route = $isQuickSearch ? 'ems_search' : Routes::DASHBOARD;
        $params = \array_filter([
            'name' => $isLanding || $isQuickSearch ? null : $dashboard->getName(),
        ]);

        if (Request::METHOD_POST === $request->getMethod()) {
            $searchForm = Type::array($request->request->all()['search_form']);
            $open = isset($searchForm['search']);
            unset($searchForm['search']);
            $uid = $this->storageManager->saveConfig($searchForm);

            return new RedirectResponse($this->router->generate($route, \array_merge($params, [
                'uid' => $uid,
                'open' => $open,
            ])));
        } elseif (!\is_string($uid)) {
            $search = $this->getDefaultSearch($options, $request->query->get('q', ''));
            $uid = $this->storageManager->saveConfig($search->jsonSerialize());

            return new RedirectResponse($this->router->generate($route, \array_merge($params, [
                'uid' => $uid,
            ])));
        }

        $page = $request->query->getInt('page', 1);
        $search = new Search();
        $form = $this->formFactory->create(SearchFormType::class, $search, [
            'dashboardOptions' => $options,
        ]);
        $data = $this->storageManager->getConfig($uid);
        $newData = $this->applyChanges($request, $data, $options);
        if ($newData) {
            $uid = $this->storageManager->saveConfig($newData);

            return new RedirectResponse($this->router->generate($route, \array_merge($params, [
                'uid' => $uid,
            ])));
        }

        $form->submit($data);
        $types = $this->contentTypeRepository->findAllAsAssociativeArray();
        $environments = $this->environmentRepository->findAllAsAssociativeArray('alias');
        $esSearch = $this->buildQuery($search, $page);
        $aggregateOptions = $this->addAggregations($esSearch, $options);
        $searchBody = \array_filter(['query' => $esSearch->getQueryArray(), 'sort' => $esSearch->getSort()]);

        try {
            $response = CommonResponse::fromResultSet($this->elasticaService->search($esSearch));
            if ($response->getTotal() >= 50000) {
                $this->logger->messageWarning(t('message.search_paging_limit_exceeded', [
                    'total' => $response->getTotal(),
                    'paging' => '50.000',
                ], 'emsco-core'));
                $lastPage = \ceil(50000 / $this->pagingSize);
            } else {
                $lastPage = \ceil($response->getTotal() / $this->pagingSize);
            }
            $indexes = $this->getMapIndexes($response, $environments);
        } catch (\Throwable $throwable) {
            $this->logger->messageError(t('message.action_error', [
                'error_message' => $throwable->getMessage(),
            ], 'emsco-core'), [
                EmsFields::LOG_EXCEPTION_FIELD => $throwable,
            ]);
            $response = null;
            $lastPage = 0;
            $indexes = [];
        }

        return new Response($this->twig->render(\sprintf('@%s/dashboard/advanced-search/render.html.twig', $this->templateNamespace), [
            'dashboard' => $dashboard,
            'form' => $form->createView(),
            'options' => $options,
            'response' => $response,
            'page' => $page,
            'lastPage' => $lastPage,
            'types' => $types,
            'indexes' => $indexes,
            'body' => $searchBody,
            'search' => $search,
            'aggregateOptions' => $aggregateOptions,
            'uid' => $uid,
            'title' => $dashboard->getLabel(),
            'breadcrumb' => $breadcrumb,
        ]));
    }

    private function getDefaultSearch(DashboardOptions $options, string $query): Search
    {
        $search = new Search();
        $search->setEnvironments(Type::array($options->offsetGet(DashboardOptions::ENVIRONMENTS) ?? []));
        $search->setContentTypes(Type::array($options->offsetGet(DashboardOptions::CONTENT_TYPES) ?? []));
        $search->setSortBy($options->getNullableString(DashboardOptions::SORT_BY));
        $search->setSortOrder($options->getNullableString(DashboardOptions::SORT_ORDER));
        $search->setMinimumShouldMatch($options->getInteger(DashboardOptions::MINIMUM_SHOULD_MATCH, 1));
        $filters = Type::array($options->offsetGet(DashboardOptions::FILTERS) ?? []);
        if ([] === $filters) {
            return $search;
        }
        $search->clearFilters();
        foreach ($filters as $filter) {
            $searchFilter = SearchFilter::fromArray($filter);
            $searchFilter->setPattern(\str_replace('%query%', $query, $searchFilter->getPattern() ?? ''));
            $search->addFilter($searchFilter);
        }

        return $search;
    }

    private function buildQuery(Search $search, int $page): CommonSearch
    {
        $esSearch = $this->searchService->generateSearch($search);
        $esSearch->setFrom(($page - 1) * $this->pagingSize);
        $esSearch->setSize(Type::integer($this->pagingSize));
        $esSearch->addTermsAggregation(self::CONTENT_TYPES_AGGREGATION, EMSSource::FIELD_CONTENT_TYPE, 15);
        $esSearch->addTermsAggregation(self::INDEXES_AGGREGATION, '_index', 15);

        return $esSearch;
    }

    /**
     * @param  array<string, string> $environments
     * @return array<string, string>
     */
    private function getMapIndexes(CommonResponse $response, array $environments): array
    {
        $indexes = $response->getAggregation(self::INDEXES_AGGREGATION);
        if (null === $indexes) {
            return [];
        }
        $mapIndex = [];
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

        return $mapIndex;
    }

    /**
     * @param  mixed[]      $data
     * @return mixed[]|null
     */
    private function applyChanges(Request $request, array $data, DashboardOptions $options): ?array
    {
        $sortByFieldName = Type::nullableString($request->query->get('sortBy'));
        if (null !== $sortByFieldName) {
            return $this->applySortChange($data, $options, $sortByFieldName);
        }
        if ($request->query->has('clearSort')) {
            unset($data['sortBy']);
            unset($data['sortOrder']);

            return $data;
        }
        $contentType = Type::nullableString($request->query->get('contentType'));
        if (\is_string($contentType)) {
            $data['contentTypes'] = [$contentType];

            return $data;
        }
        $environment = Type::nullableString($request->query->get('environment'));
        if (\is_string($environment)) {
            $data['environments'] = [$environment];

            return $data;
        }
        $pattern = Type::nullableString($request->query->get('pattern'));
        $field = Type::nullableString($request->query->get('field'));
        if (\is_string($pattern) || \is_string($field)) {
            return $this->applyAddFilter($request, $data, $field ?? '', $pattern ?? '');
        }
        $removeFilter = Type::nullableString($request->query->get('removeFilter'));
        if (\is_string($removeFilter)) {
            unset($data['filters'][(int) $removeFilter]);
            $data['filters'] = \array_values($data['filters']);

            return $data;
        }

        return null;
    }

    /**
     * @param  mixed[] $data
     * @return mixed[]
     */
    private function applySortChange(array $data, DashboardOptions $options, string $sortByFieldName): array
    {
        foreach ($options->getArray(DashboardOptions::SORT_OPTIONS) as $sortBy) {
            if ($sortByFieldName !== ($sortBy['field'] ?? null)) {
                continue;
            }
            if ($sortByFieldName === ($data['sortBy'] ?? null)) {
                $data['sortOrder'] = ('desc' === $data['sortOrder']) ? 'asc' : 'desc';
            } else {
                $data['sortBy'] = $sortBy['field'];
                $data['sortOrder'] = ($sortBy['inverted'] ?? null) ? 'desc' : 'asc';
            }

            return $data;
        }
        throw new \RuntimeException(\sprintf('Sort option %s not found', $sortByFieldName));
    }

    /**
     * @param  mixed[] $data
     * @return mixed[]
     */
    private function applyAddFilter(Request $request, array $data, string $field, string $pattern): array
    {
        $filters = \array_values($data['filters'] ?? []);
        $filters[] = [
            'booleanClause' => $request->query->get('clause') ?? 'must',
            'boost' => $request->query->get('boost') ?? '',
            'operator' => $request->query->get('operator') ?? 'term',
            'field' => $field,
            'pattern' => $pattern,
        ];
        $data['filters'] = $filters;

        return $data;
    }

    /**
     * @return mixed[]
     */
    private function addAggregations(CommonSearch $esSearch, DashboardOptions $options): array
    {
        $aggregations = [];
        foreach ($options->getArray(DashboardOptions::AGGREGATE_OPTIONS) as $aggregateOption) {
            $id = Uuid::uuid4()->toString();
            $aggregations[$id] = $aggregateOption;
            $aggregation = new ElasticaAggregation($id);
            $config = self::convertAggregation(Type::string($aggregateOption['config'] ?? null));
            foreach ($config as $basename => $param) {
                $aggregation->setConfig($basename, $param);
            }
            $esSearch->addAggregation($aggregation);
        }

        return $aggregations;
    }

    /**
     * @return mixed[]
     */
    private static function convertAggregation(string $config): array
    {
        $recursiveCheck = function (array &$json) use (&$recursiveCheck) {
            foreach ($json as $field => &$data) {
                if ('reverse_nested' === $field && empty($data)) {
                    $data = new \stdClass();
                } elseif (\is_array($data)) {
                    $recursiveCheck($data);
                }
            }
        };
        $json = Json::decode($config);
        $recursiveCheck($json);

        return $json;
    }

    private function exportResult(Request $request, Dashboard $dashboard): Response
    {
        $search = new Search();
        $form = $this->formFactory->create(SearchFormType::class, $search, [
            'dashboardOptions' => $dashboard->getOptions(),
        ]);
        $form->handleRequest($request);
        $esSearch = $this->buildQuery($search, 1);
        $this->addAggregations($esSearch, $dashboard->getOptions());
        $response = CommonResponse::fromResultSet($this->elasticaService->search($esSearch));
        $searchBody = \array_filter(['query' => $esSearch->getQueryArray(), 'sort' => $esSearch->getSort()]);
        $types = $this->contentTypeRepository->findAllAsAssociativeArray();

        $exportForms = [];
        $contentTypes = $response->getAggregation(self::CONTENT_TYPES_AGGREGATION)?->getBuckets() ?? [];
        foreach ($contentTypes as $bucket) {
            if (null === $name = $bucket->getKey()) {
                continue;
            }
            $contentType = $types[$name];

            $exportForm = $this->formFactory->create(ExportDocumentsType::class, new ExportDocuments(
                $contentType,
                $this->router->generate('emsco_search_export', ['contentType' => $contentType->getId()]),
                Json::encode($searchBody)
            ));

            $exportForms[] = [
                'form' => $exportForm->createView(),
                'title' => t('type.export', ['type' => 'documents', 'count' => $bucket->getCount(), 'singular' => $contentType->getSingularName(), 'plural' => $contentType->getPluralName()], 'emsco-core'),
                'icon' => $contentType->getIcon(),
            ];
        }

        return new Response($this->twig->render(\sprintf('@%s/elasticsearch/export-search.html.twig', $this->templateNamespace), [
            'forms' => $exportForms,
            'title' => t('key.export_documents', [], 'emsco-core'),
            'subTitle' => t('type.title_sub', ['type' => 'search'], 'emsco-core'),
        ]));
    }
}
