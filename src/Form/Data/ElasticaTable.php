<?php

namespace EMS\CoreBundle\Form\Data;

use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Elasticsearch\Response\Response;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ElasticaTable extends TableAbstract
{
    const COLUMNS = 'columns';
    private ElasticaService $elasticaService;
    /** @var string[] */
    private array $aliases;
    /** @var string[] */
    private array $contentTypeNames;
    private ?int $count = null;
    private ?int $totalCount = null;

    /**
     * @param string[] $aliases
     * @param string[] $contentTypeNames
     */
    public function __construct(ElasticaService $elasticaService, string $ajaxUrl, array $aliases, array $contentTypeNames)
    {
        parent::__construct($ajaxUrl, 0, 0);
        $this->elasticaService = $elasticaService;
        $this->aliases = $aliases;
        $this->contentTypeNames = $contentTypeNames;
    }

    /**
     * @param string[]             $aliases
     * @param string[]             $contentTypeNames
     * @param array<string, mixed> $options
     */
    public static function fromConfig(ElasticaService $elasticaService, string $ajaxUrl, array $aliases, array $contentTypeNames, array $options): ElasticaTable
    {
        $datatable = new self($elasticaService, $ajaxUrl, $aliases, $contentTypeNames);
        $options = self::resolveOptions($options);
        foreach ($options[self::COLUMNS] as $column) {
            $datatable->addColumnDefinition(new TemplateTableColumn($column));
        }

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
            $search = $this->elasticaService->convertElasticsearchBody($this->aliases, $this->contentTypeNames, []);
        } else {
            $search = $this->elasticaService->convertElasticsearchBody($this->aliases, $this->contentTypeNames, []);
        }
        $search->setFrom($this->getFrom());
        $search->setSize($this->getSize());
        $orderField = $this->getOrderField();
        if (null !== $orderField) {
            $search->setSort([
                $orderField => $this->getOrderDirection(),
            ]);
        }

        return $search;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array{columns: array}
     */
    private static function resolveOptions(array $options)
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setDefaults([
                self::COLUMNS => [],
            ])
            ->setAllowedTypes(self::COLUMNS, ['array'])
        ;
        /** @var array{columns: array} $resolvedParameter */
        $resolvedParameter = $resolver->resolve($options);

        return $resolvedParameter;
    }
}
