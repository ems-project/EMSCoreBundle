<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use EMS\CoreBundle\Service\QueryServiceInterface;

class QueryTable extends TableAbstract
{
    private bool $loadAll;
    private bool $massAction = true;
    private string $idField = 'id';

    /**
     * @param mixed $context
     */
    public function __construct(private readonly string $templateNamespace, private readonly QueryServiceInterface $service, private readonly string $queryName, string $ajaxUrl, private $context = null, int $loadAllMaxRow = 400)
    {
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

    public function setMassAction(bool $massAction): void
    {
        $this->massAction = $massAction;
    }

    public function setIdField(string $idField): void
    {
        $this->idField = $idField;
    }

    public function getIdField(): string
    {
        return $this->idField;
    }

    /**
     * @return \Traversable<string, QueryRow>
     */
    public function getIterator(): \Traversable
    {
        foreach ($this->service->query($this->getFrom(), $this->getSize(), $this->getOrderField(), $this->getOrderDirection(), $this->getSearchValue(), $this->context) as $data) {
            $id = $data[$this->idField] ?? null;
            if (null === $id) {
                continue;
            }
            yield \strval($id) => new QueryRow($data);
        }
    }

    public function count(): int
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
        $min = $this->massAction ? 1 : 0;
        if ($this->totalCount() <= $min) {
            return false;
        }
        foreach ($this->getTableActions() as $action) {
            return true;
        }

        return false;
    }

    public function getRowTemplate(): string
    {
        return \sprintf("{%%- use '@$this->templateNamespace/datatable/row.json.twig' -%%}{{ block('emsco_datatable_row') }}");
    }

    public function getAttributeName(): string
    {
        return $this->queryName;
    }
}
