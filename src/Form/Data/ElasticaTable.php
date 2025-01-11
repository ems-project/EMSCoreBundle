<?php

namespace EMS\CoreBundle\Form\Data;

use EMS\CommonBundle\Elasticsearch\Document\Document;
use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Elasticsearch\QueryStringEscaper;
use EMS\CommonBundle\Elasticsearch\Response\Response;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use EMS\Helpers\Standard\Json;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function Symfony\Component\String\u;

class ElasticaTable extends TableAbstract
{
    private const string COLUMNS = 'columns';
    private const string QUERY = 'query';
    private const string EMPTY_QUERY = 'empty_query';
    private const string FRONTEND_OPTIONS = 'frontendOptions';
    private const string ASC_MISSING_VALUES_POSITION = 'asc_missing_values_position';
    private const string DESC_MISSING_VALUES_POSITION = 'desc_missing_values_position';
    private const string DEFAULT_SORT = 'default_sort';
    final public const string FILENAME = 'filename';
    final public const string DISPOSITION = 'disposition';
    final public const string SHEET_NAME = 'sheet_name';
    private const string ROW_CONTEXT = 'row_context';
    final public const string PROTECTED = 'protected';
    public const CHECKABLE = 'checkable';
    public const ACTIONS = 'actions';
    public const ID = 'id';
    private ?int $count = null;
    private ?int $totalCount = null;

    /**
     * @param string[]              $aliases
     * @param string[]              $contentTypeNames
     * @param array<string, string> $defaultSort
     */
    public function __construct(
        public readonly string $id,
        private readonly string $templateNamespace,
        private readonly ElasticaService $elasticaService,
        string $ajaxUrl,
        private readonly array $aliases,
        private readonly array $contentTypeNames,
        private readonly string $emptyQuery,
        private readonly string $query,
        private readonly string $ascMissingValuesPosition,
        private readonly string $descMissingValuesPosition,
        string $filename,
        string $disposition,
        string $sheetName,
        private readonly string $rowContext,
        private readonly array $defaultSort,
        private readonly bool $protected,
        private readonly bool $checkable,
    ) {
        parent::__construct($ajaxUrl, 0, 0);
        $this->setExportFileName($filename);
        $this->setExportDisposition($disposition);
        $this->setExportSheetName($sheetName);
    }

    /**
     * @param string[]             $aliases
     * @param string[]             $contentTypeNames
     * @param array<string, mixed> $options
     */
    public static function fromConfig(string $templateNamespace, ElasticaService $elasticaService, string $ajaxUrl, array $aliases, array $contentTypeNames, array $options): ElasticaTable
    {
        $options = self::resolveOptions($options);
        $datatable = new self(
            id: $options[self::ID],
            templateNamespace: $templateNamespace,
            elasticaService: $elasticaService,
            ajaxUrl: $ajaxUrl,
            aliases: $aliases,
            contentTypeNames: $contentTypeNames,
            emptyQuery: $options[self::EMPTY_QUERY],
            query: $options[self::QUERY],
            ascMissingValuesPosition: $options[self::ASC_MISSING_VALUES_POSITION],
            descMissingValuesPosition: $options[self::DESC_MISSING_VALUES_POSITION],
            filename: $options[self::FILENAME],
            disposition: $options[self::DISPOSITION],
            sheetName: $options[self::SHEET_NAME],
            rowContext: $options[self::ROW_CONTEXT],
            defaultSort: $options[self::DEFAULT_SORT],
            protected: $options[self::PROTECTED],
            checkable: $options[self::CHECKABLE]
        );
        foreach ($options[self::COLUMNS] as $column) {
            $datatable->addColumnDefinition(new TemplateTableColumn($column));
        }

        foreach ($options[self::ACTIONS] as $action) {
            $massAction = $datatable->addMassAction($action['name'], $action['label'], $action['icon'], $action['confirm'] ?? null);
            if (isset($action['class'])) {
                $massAction->setCssClass($action['class']);
            }
        }

        $datatable->setExtraFrontendOption($options[self::FRONTEND_OPTIONS]);

        return $datatable;
    }

    /**
     * @return \Generator<string, ElasticaRow>
     */
    public function scroll(): \Generator
    {
        $search = $this->getSearch($this->getSearchValue());
        $search->setSize(100);
        $scroll = $this->elasticaService->scroll($search);

        foreach ($scroll as $resultSet) {
            foreach ($resultSet->getResults() as $result) {
                $emsDocument = Document::fromResult($result);
                yield $emsDocument->getEmsId() => new ElasticaRow($emsDocument);
            }
        }
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        $search = $this->getSearch($this->getSearchValue());
        $resultSet = $this->elasticaService->search($search);
        $index = $this->getFrom();
        /** @var DocumentInterface $document */
        foreach (Response::fromResultSet($resultSet)->getDocuments() as $document) {
            yield $document->getEmsId() => new ElasticaRow($document);
            ++$index;
        }
    }

    #[\Override]
    public function count(): int
    {
        if (null === $this->count) {
            $search = $this->getSearch($this->getSearchValue());
            $search->setSize(0);
            $resultSet = $this->elasticaService->search($search);
            $this->count = Response::fromResultSet($resultSet)->getTotal();
        }

        return $this->count;
    }

    #[\Override]
    public function supportsTableActions(): bool
    {
        return $this->checkable;
    }

    #[\Override]
    public function totalCount(): int
    {
        if (null === $this->totalCount) {
            $search = $this->getSearch('');
            $search->setSize(0);
            $resultSet = $this->elasticaService->search($search);
            $this->totalCount = Response::fromResultSet($resultSet)->getTotal();
        }

        return $this->totalCount;
    }

    #[\Override]
    public function getAttributeName(): string
    {
        return 'dataLink';
    }

    public function getRowContext(): string
    {
        return $this->rowContext;
    }

    private function getSearch(string $searchValue): Search
    {
        if (\strlen($searchValue) > 0) {
            $search = $this->elasticaService->convertElasticsearchBody($this->aliases, $this->contentTypeNames, ['query' => $this->getQuery($searchValue)]);
        } else {
            $search = $this->elasticaService->convertElasticsearchBody($this->aliases, $this->contentTypeNames, ['query' => $this->emptyQuery]);
        }
        $search->setFrom($this->getFrom());
        $search->setSize($this->getSize());
        $orderField = $this->getOrderField();
        if (null !== $orderField) {
            $search->setSort([
                $orderField => [
                    'missing' => 0 === \strcasecmp($this->getOrderDirection(), 'desc') ? $this->descMissingValuesPosition : $this->ascMissingValuesPosition,
                    'order' => $this->getOrderDirection(),
                ],
            ]);
        } elseif ($this->defaultSort) {
            $search->setSort($this->defaultSort);
        }

        return $search;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{
     *     id: string,
     *     columns: array<mixed>,
     *     actions: array<mixed>,
     *     query: string,
     *     empty_query: string,
     *     frontendOptions: array<mixed>,
     *     asc_missing_values_position: string,
     *     desc_missing_values_position: string,
     *     filename: string,
     *     disposition: string,
     *     sheet_name: string,
     *     row_context: string,
     *     default_sort: array<string, string>,
     *     protected: bool,
     *     checkable: bool
     * }
     */
    private static function resolveOptions(array $options): array
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setDefaults([
                self::ID => 'elastica-datatable',
                self::COLUMNS => [],
                self::ACTIONS => [],
                self::EMPTY_QUERY => [],
                self::QUERY => [
                    'query_string' => [
                        'query' => '%query%',
                    ],
                ],
                self::FRONTEND_OPTIONS => [],
                self::ASC_MISSING_VALUES_POSITION => '_last',
                self::DESC_MISSING_VALUES_POSITION => '_first',
                self::FILENAME => 'datatable',
                self::DISPOSITION => 'attachment',
                self::SHEET_NAME => 'Sheet',
                self::ROW_CONTEXT => '',
                self::DEFAULT_SORT => [],
                self::PROTECTED => true,
                self::CHECKABLE => false,
            ])
            ->setAllowedTypes(self::ID, ['string'])
            ->setAllowedTypes(self::COLUMNS, ['array'])
            ->setAllowedTypes(self::ACTIONS, ['array'])
            ->setAllowedTypes(self::QUERY, ['array', 'string'])
            ->setAllowedTypes(self::ASC_MISSING_VALUES_POSITION, ['string'])
            ->setAllowedTypes(self::DESC_MISSING_VALUES_POSITION, ['string'])
            ->setAllowedTypes(self::FILENAME, ['string'])
            ->setAllowedTypes(self::DISPOSITION, ['string'])
            ->setAllowedTypes(self::SHEET_NAME, ['string'])
            ->setAllowedTypes(self::ROW_CONTEXT, ['string'])
            ->setAllowedTypes(self::DEFAULT_SORT, ['array'])
            ->setAllowedTypes(self::PROTECTED, ['bool'])
            ->setAllowedTypes(self::CHECKABLE, ['bool'])
            ->setAllowedValues(self::ASC_MISSING_VALUES_POSITION, ['_last', '_first'])
            ->setAllowedValues(self::DESC_MISSING_VALUES_POSITION, ['_last', '_first'])
            ->setNormalizer(self::QUERY, function (Options $options, $value) {
                if (\is_array($value)) {
                    $value = Json::encode($value);
                }
                if (!\is_string($value)) {
                    throw new \RuntimeException('Unexpected query type');
                }

                return $value;
            })
            ->setNormalizer(self::EMPTY_QUERY, function (Options $options, $value) {
                if (\is_array($value)) {
                    $value = Json::encode($value);
                }
                if (!\is_string($value)) {
                    throw new \RuntimeException('Unexpected emptyQuery type');
                }

                return $value;
            })
            ->setNormalizer(self::DEFAULT_SORT, function (Options $options, $value) {
                if (!\is_array($value)) {
                    throw new \RuntimeException('Unexpected non array value');
                }
                foreach ($value as $field => $order) {
                    if (!\is_string($field)) {
                        throw new \RuntimeException('Unexpected non string field');
                    }
                    if (!\in_array($order, ['asc', 'desc'])) {
                        throw new \RuntimeException('Unexpected order value. Expect `desc`or `asc`.');
                    }
                }

                return $value;
            })
        ;
        /** @var array{id: string, columns: array<mixed>, actions: array<mixed>, query: string, empty_query: string, frontendOptions: array<mixed>, asc_missing_values_position: string, desc_missing_values_position: string, filename: string, disposition: string, sheet_name: string, row_context: string, default_sort: array<string, string>, protected: bool, checkable: bool} $resolvedParameter */
        $resolvedParameter = $resolver->resolve($options);

        return $resolvedParameter;
    }

    private function getQuery(string $searchValue): string
    {
        return u($this->query)
            ->replace('%query%', Json::escape($searchValue))
            ->replace('%query_escaped%', Json::escape(QueryStringEscaper::escape($searchValue)))
            ->toString();
    }

    #[\Override]
    public function getRowTemplate(): string
    {
        return \sprintf("{%%- use '@$this->templateNamespace/datatable/row.json.twig' -%%}%s{{ block('emsco_datatable_row') }}", $this->getRowContext());
    }

    public function isProtected(): bool
    {
        return $this->protected;
    }
}
