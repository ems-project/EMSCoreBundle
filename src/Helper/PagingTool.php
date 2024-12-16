<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Helper;

use Doctrine\ORM\EntityRepository;
use EMS\Helpers\Standard\Type;
use Symfony\Component\HttpFoundation\Request;

class PagingTool
{
    private readonly int $lastPage;
    private readonly int $page;
    private readonly string $orderField;
    private readonly string $orderDirection;

    /** @var array<mixed> */
    private readonly array $data;

    /**
     * @param EntityRepository<object> $repository
     */
    public function __construct(
        Request $request,
        private readonly EntityRepository $repository,
        private readonly string $paginationPath,
        string $defaultOrderField,
        private readonly int $pageSize,
    ) {
        $this->lastPage = (int) \ceil(\count($repository->findAll()) / $pageSize);
        $this->page = $request->query->getInt('page', 1);
        $this->orderField = Type::string($request->query->get('orderField', $defaultOrderField));
        $this->orderDirection = $request->query->getAlpha('orderDirection', 'asc');

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
