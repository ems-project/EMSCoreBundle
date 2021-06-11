<?php

namespace EMS\CoreBundle\Form\Data;

use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Elasticsearch\Response\Response;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ElasticaTable extends TableAbstract
{
    private const COLUMNS = 'columns';
    private const QUERY = 'query';
    private const EMPTY_QUERY = 'empty_query';
    private const FRONTEND_OPTIONS = 'frontendOptions';
    private const ASC_MISSING_VALUES_POSITION = 'asc_missing_values_position';
    private const DESC_MISSING_VALUES_POSITION = 'desc_missing_values_position';
    public const FILENAME = 'filename';
    public const DISPOSITION = 'disposition';
    public const SHEET_NAME = 'sheet_name';
    private ElasticaService $elasticaService;
    /** @var string[] */
    private array $aliases;
    /** @var string[] */
    private array $contentTypeNames;
    private ?int $count = null;
    private ?int $totalCount = null;
    private string $emptyQuery;
    private string $query;
    private string $ascMissingValuesPosition;
    private string $descMissingValuesPosition;
    private string $filename;
    private string $disposition;
    private $sheetName;

    /**
     * @param string[] $aliases
     * @param string[] $contentTypeNames
     */
    public function __construct(ElasticaService $elasticaService, string $ajaxUrl, array $aliases, array $contentTypeNames, string $emptyQuery, string $query, string $ascMissingValuesPosition, string $descMissingValuesPosition, string $filename, string $disposition, string $sheetName)
    {
        parent::__construct($ajaxUrl, 0, 0);
        $this->elasticaService = $elasticaService;
        $this->aliases = $aliases;
        $this->contentTypeNames = $contentTypeNames;
        $this->emptyQuery = $emptyQuery;
        $this->query = $query;
        $this->ascMissingValuesPosition = $ascMissingValuesPosition;
        $this->descMissingValuesPosition = $descMissingValuesPosition;
        $this->filename = $filename;
        $this->disposition = $disposition;
        $this->sheetName = $sheetName;
    }

    /**
     * @param string[]             $aliases
     * @param string[]             $contentTypeNames
     * @param array<string, mixed> $options
     */
    public static function fromConfig(ElasticaService $elasticaService, string $ajaxUrl, array $aliases, array $contentTypeNames, array $options): ElasticaTable
    {
        $options = self::resolveOptions($options);
        $datatable = new self($elasticaService, $ajaxUrl, $aliases, $contentTypeNames, $options[self::EMPTY_QUERY], $options[self::QUERY], $options[self::ASC_MISSING_VALUES_POSITION], $options[self::DESC_MISSING_VALUES_POSITION], $options[self::FILENAME], $options[self::DISPOSITION], $options[self::SHEET_NAME]);
        foreach ($options[self::COLUMNS] as $column) {
            $datatable->addColumnDefinition(new TemplateTableColumn($column));
        }
        $datatable->setExtraFrontendOption($options[self::FRONTEND_OPTIONS]);

        return $datatable;
    }

    public function getIterator()
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

    public function count()
    {
        if (null === $this->count) {
            $search = $this->getSearch($this->getSearchValue());
            $search->setSize(0);
            $resultSet = $this->elasticaService->search($search);
            $this->count = Response::fromResultSet($resultSet)->getTotal();
        }

        return $this->count;
    }

    public function supportsTableActions(): bool
    {
        return false;
    }

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

    public function getAttributeName(): string
    {
        return 'dataLink';
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getSheetName(): string
    {
        return $this->sheetName;
    }

    public function getDisposition(): string
    {
        return $this->disposition;
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
        }

        return $search;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{columns: array, query: string, empty_query: string, frontendOptions: array, asc_missing_values_position: string, desc_missing_values_position: string, filename: string, disposition: string, sheet_name: string}
     */
    private static function resolveOptions(array $options)
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setDefaults([
                self::COLUMNS => [],
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
            ])
            ->setAllowedTypes(self::COLUMNS, ['array'])
            ->setAllowedTypes(self::QUERY, ['array', 'string'])
            ->setAllowedTypes(self::ASC_MISSING_VALUES_POSITION, ['string'])
            ->setAllowedTypes(self::DESC_MISSING_VALUES_POSITION, ['string'])
            ->setAllowedTypes(self::FILENAME, ['string'])
            ->setAllowedTypes(self::DISPOSITION, ['string'])
            ->setAllowedTypes(self::SHEET_NAME, ['string'])
            ->setAllowedValues(self::ASC_MISSING_VALUES_POSITION, ['_last', '_first'])
            ->setAllowedValues(self::DESC_MISSING_VALUES_POSITION, ['_last', '_first'])
            ->setNormalizer(self::QUERY, function (Options $options, $value) {
                if (\is_array($value)) {
                    $value = \json_encode($value);
                }
                if (!\is_string($value)) {
                    throw new \RuntimeException('Unexpected query type');
                }

                return $value;
            })
            ->setNormalizer(self::EMPTY_QUERY, function (Options $options, $value) {
                if (\is_array($value)) {
                    $value = \json_encode($value);
                }
                if (!\is_string($value)) {
                    throw new \RuntimeException('Unexpected emptyQuery type');
                }

                return $value;
            })
        ;
        /** @var array{columns: array, query: string, empty_query: string, frontendOptions: array, asc_missing_values_position: string, desc_missing_values_position: string, filename: string, disposition: string, sheet_name: string} $resolvedParameter */
        $resolvedParameter = $resolver->resolve($options);

        return $resolvedParameter;
    }

    private function getQuery(string $searchValue): string
    {
        $encoded = \json_encode($searchValue);
        if (false === $encoded || \strlen($encoded) < 2) {
            throw new \RuntimeException(\sprintf('Unexpected error while JSON encoding "%s"', $searchValue));
        }
        $encoded = \substr($encoded, 1, \strlen($encoded) - 2);

        return \str_replace('%query%', $encoded, $this->query);
    }
}
