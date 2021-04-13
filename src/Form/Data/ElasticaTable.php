<?php

namespace EMS\CoreBundle\Form\Data;

use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;
use EMS\CommonBundle\Elasticsearch\Response\Response;
use EMS\CommonBundle\Search\Search;
use EMS\CommonBundle\Service\ElasticaService;

class ElasticaTable extends TableAbstract
{
    private ElasticaService $elasticaService;
    /** @var string[] */
    private array $indexes;
    /** @var string[] */
    private array $contentTypeNames;
    private ?int $count = null;
    private ?int $totalCount = null;

    /**
     * @param string[] $indexes
     * @param string[] $contentTypeNames
     */
    public function __construct(ElasticaService $elasticaService, array $indexes, array $contentTypeNames)
    {
        parent::__construct(null, 0, 0);
        $this->elasticaService = $elasticaService;
        $this->indexes = $indexes;
        $this->contentTypeNames = $contentTypeNames;
    }

    /**
     * @param string[]             $indexes
     * @param string[]             $contentTypeNames
     * @param array<string, mixed> $jsonConfig
     */
    public static function fromConfig(ElasticaService $elasticaService, array $indexes, array $contentTypeNames, array $jsonConfig): ElasticaTable
    {
        return new self($elasticaService, $indexes, $contentTypeNames);
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
            $search = $this->elasticaService->convertElasticsearchBody($this->indexes, $this->contentTypeNames, []);
        } else {
            $search = $this->elasticaService->convertElasticsearchBody($this->indexes, $this->contentTypeNames, []);
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
}
