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
    private ElasticaService $elasticaService;
    /** @var string[] */
    private array $aliases;
    /** @var string[] */
    private array $contentTypeNames;
    private ?int $count = null;
    private ?int $totalCount = null;
    private string $emptyQuery;
    private string $query;

    /**
     * @param string[] $aliases
     * @param string[] $contentTypeNames
     */
    public function __construct(ElasticaService $elasticaService, string $ajaxUrl, array $aliases, array $contentTypeNames, string $emptyQuery, string $query)
    {
        parent::__construct($ajaxUrl, 0, 0);
        $this->elasticaService = $elasticaService;
        $this->aliases = $aliases;
        $this->contentTypeNames = $contentTypeNames;
        $this->emptyQuery = $emptyQuery;
        $this->query = $query;
    }

    /**
     * @param string[]             $aliases
     * @param string[]             $contentTypeNames
     * @param array<string, mixed> $options
     */
    public static function fromConfig(ElasticaService $elasticaService, string $ajaxUrl, array $aliases, array $contentTypeNames, array $options): ElasticaTable
    {
        $options = self::resolveOptions($options);
        $datatable = new self($elasticaService, $ajaxUrl, $aliases, $contentTypeNames, $options[self::EMPTY_QUERY], $options[self::QUERY]);
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
                    'missing' => 0 === \strcasecmp($this->getOrderDirection(), 'desc') ? '_first' : '_last',
                    'order' => $this->getOrderDirection(),
                ],
            ]);
        }

        return $search;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{columns: array, query: string, empty_query: string, frontendOptions: array}
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
            ])
            ->setAllowedTypes(self::COLUMNS, ['array'])
            ->setAllowedTypes(self::QUERY, ['array', 'string'])
            ->setAllowedTypes(self::QUERY, ['array', 'string'])
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
        /** @var array{columns: array, query: string, empty_query: string, frontendOptions: array} $resolvedParameter */
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
