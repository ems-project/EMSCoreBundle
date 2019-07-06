<?php

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use EMS\CoreBundle\Helper\IndexView;
use Symfony\Component\HttpFoundation\RequestStack;
use EMS\CoreBundle\Helper\PagingTool;

class HelperService
{
    /**@var Registry $doctrine */
    protected $doctrine;
    private $pagingSize;
    private $requestStack;
    
    public function __construct(Registry $doctrine, RequestStack $requestStack, $pagingSize)
    {
        $this->doctrine = $doctrine;
        $this->pagingSize = $pagingSize;
        $this->requestStack = $requestStack;
    }

    public function getPagingTool($entityName, $route, $defaultOrderField)
    {
        return new PagingTool($this->requestStack->getCurrentRequest(), $this->doctrine->getRepository($entityName), $route, $defaultOrderField, $this->pagingSize);
    }

    public function getIndexView($entityName, $icon)
    {
        return new IndexView($entityName, $this->doctrine->getRepository($entityName), $icon);
    }
}
