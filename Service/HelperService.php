<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\HttpFoundation\RequestStack;
use EMS\CoreBundle\Helper\PagingTool;

class HelperService
{
    /**@var Registry $doctrine */
    protected $doctrine;
    
    
    public function __construct(Registry $doctrine, RequestStack $requestStack, $paging_size)
    {
        $this->doctrine = $doctrine;
        $this->paging_size = $paging_size;
        $this->requestStack = $requestStack;
    }
    
    public function getPagingTool($entityName, $route, $defaultOrderField)
    {
        return new PagingTool($this->requestStack->getCurrentRequest(), $this->doctrine->getRepository($entityName), $route, $defaultOrderField, $this->paging_size);
    }
}
