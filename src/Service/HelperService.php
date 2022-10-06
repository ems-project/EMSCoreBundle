<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\Helper\PagingTool;
use Symfony\Component\HttpFoundation\RequestStack;

class HelperService
{
    private Registry $doctrine;
    private int $pagingSize;
    private RequestStack $requestStack;

    public function __construct(Registry $doctrine, RequestStack $requestStack, int $pagingSize)
    {
        $this->doctrine = $doctrine;
        $this->pagingSize = $pagingSize;
        $this->requestStack = $requestStack;
    }

    /**
     * @param class-string $entityName
     */
    public function getPagingTool(string $entityName, string $route, string $defaultOrderField): PagingTool
    {
        if (null === $request = $this->requestStack->getCurrentRequest()) {
            throw new \RuntimeException('No current request');
        }

        $repository = $this->doctrine->getRepository($entityName);
        if (!$repository instanceof EntityRepository) {
            throw new \RuntimeException('Invalid repository');
        }

        return new PagingTool(
            $request,
            $repository,
            $route,
            $defaultOrderField,
            $this->pagingSize
        );
    }
}
