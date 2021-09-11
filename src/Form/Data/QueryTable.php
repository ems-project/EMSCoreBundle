<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use EMS\CoreBundle\Service\QueryServiceInterface;

class QueryTable extends TableAbstract
{
    private bool $loadAll;
    /**
     * @var mixed|null
     */
    private $context;
    private QueryServiceInterface $service;
    private $queryName;

    /**
     * @param mixed $context
     */
    public function __construct(QueryServiceInterface $service, string $queryName, string $ajaxUrl, $context = null, int $loadAllMaxRow = 400)
    {
        $this->context = $context;
        $this->service = $service;
        $this->queryName = $queryName;

        if ($this->count() > $loadAllMaxRow) {
            parent::__construct($ajaxUrl, 0, 0);
            $this->loadAll = false;
        } else {
            parent::__construct(null, 0, $loadAllMaxRow);
            $this->loadAll = true;
        }
    }

    /**
     * @return mixed|null
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @return \IteratorAggregate<string, QueryRow>
     */
    public function getIterator(): iterable
    {
        foreach ($this->service->query($this->getFrom(), $this->getSize(), $this->getOrderField(), $this->getOrderDirection(), $this->getSearchValue(), $this->context) as $data) {
            $id = $data['id'] ?? null;
            if (null === $id) {
                continue;
            }
            yield \strval($id) => new QueryRow($data);
        }
    }

    public function count()
    {
        return $this->service->countQuery($this->getSearchValue(), $this->context);
    }

    public function totalCount(): int
    {
        return $this->service->countQuery('', $this->context);
    }

    public function supportsTableActions(): bool
    {
        if (!$this->loadAll) {
            return false;
        }
        if ($this->totalCount() <= 1) {
            return false;
        }
        foreach ($this->getTableActions() as $action) {
            return true;
        }

        return false;
    }

    public function getRowTemplate(): string
    {
        return \sprintf("{%%- use '@EMSCore/datatable/row.json.twig' -%%}{{ block('emsco_datatable_row') }}");
    }

    public function getAttributeName(): string
    {
        return $this->queryName;
    }
}
