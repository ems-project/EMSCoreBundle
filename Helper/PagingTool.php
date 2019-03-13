<?php

namespace EMS\CoreBundle\Helper;



use Doctrine\ORM\EntityRepository;
use Symfony\Component\HttpFoundation\Request;

class PagingTool {
    
    /** @var EntityRepository $repository */
    private $repository;
    private $pageSize;
    private $lastPage;
    private $page;
    private $orderField;
    private $orderDirection;
    private $paginationPath;
    
    
    public function __construct(Request $request, EntityRepository $repository, $paginationPath, $defaultOrderField, $pageSize) {        
        $this->repository = $repository;
        $this->pageSize = $pageSize;
        $this->lastPage = ceil(count($repository->findAll())/$pageSize);
        $this->page = $request->query->get('page', 1);
        $this->orderField= $request->query->get('orderField', $defaultOrderField);
        $this->orderDirection= $request->query->get('orderDirection', 'asc');
        $this->paginationPath = $paginationPath;
        
        $this->data = $this->repository->findBy([], [$this->orderField => $this->orderDirection], $pageSize, ($this->page-1)*$this->pageSize);
    }
    
    public function getData() {
        return $this->data;
    }
    
    public function getPageSize() {
        return $this->pageSize;
    }
    
    public function getLastPage() {
        return $this->lastPage;
    }
    
    public function getPage() {
        return $this->page;
    }
    
    public function getOrderDirection() {
        return $this->orderDirection;
    }
    
    public function getOrderField() {
        return $this->orderField;
    }
    
    public function getPaginationPath() {
        return $this->paginationPath;
    }
    
}
    