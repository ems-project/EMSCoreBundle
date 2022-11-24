<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Helper;

use Doctrine\ORM\EntityRepository;
use EMS\Helpers\Standard\Type;
use Symfony\Component\HttpFoundation\Request;

class PagingTool
{
    /** @var EntityRepository<object> */
    private EntityRepository $repository;
    private int $pageSize;
    private int $lastPage;
    private int $page;
    private string $orderField;
    private string $orderDirection;
    private string $paginationPath;

    /** @var array<mixed> */
    private array $data;

    /**
     * @param EntityRepository<object> $repository
     */
    public function __construct(
        Request $request,
        EntityRepository $repository,
        string $paginationPath,
        string $defaultOrderField,
        int $pageSize
    ) {
        $this->repository = $repository;
        $this->pageSize = $pageSize;
        $this->lastPage = (int) \ceil(\count($repository->findAll()) / $pageSize);
        $this->page = $request->query->getInt('page', 1);
        $this->orderField = Type::string($request->query->get('orderField', $defaultOrderField));
        $this->orderDirection = $request->query->getAlpha('orderDirection', 'asc');
        $this->paginationPath = $paginationPath;

        $this->data = $this->repository->findBy([], [$this->orderField => $this->orderDirection], $pageSize, ($this->page - 1) * $this->pageSize);
    }

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    public function getLastPage(): int
    {
        return $this->lastPage;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getOrderDirection(): string
    {
        return $this->orderDirection;
    }

    public function getOrderField(): string
    {
        return $this->orderField;
    }

    public function getPaginationPath(): string
    {
        return $this->paginationPath;
    }
}
