<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityRepository;
use EMS\CoreBundle\Helper\PagingTool;
use Symfony\Component\HttpFoundation\RequestStack;

class HelperService
{
    public function __construct(private readonly Registry $doctrine, private readonly RequestStack $requestStack, private readonly int $pagingSize)
    {
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
