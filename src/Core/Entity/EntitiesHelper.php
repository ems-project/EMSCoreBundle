<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Entity;

use EMS\CoreBundle\Exception\EntityServiceNotFoundException;
use EMS\CoreBundle\Service\EntityServiceInterface;

class EntitiesHelper
{
    /** @var EntityServiceInterface[] */
    private array $entityServices = [];

    /**
     * @param iterable<EntityServiceInterface> $entityServices
     */
    public function __construct(iterable $entityServices)
    {
        foreach ($entityServices as $entityService) {
            $this->entityServices[$entityService->getEntityName()] = $entityService;
        }
    }

    public function getEntityService(string $entityName): EntityServiceInterface
    {
        if (!isset($this->entityServices[$entityName])) {
            throw new EntityServiceNotFoundException($entityName);
        }

        return $this->entityServices[$entityName];
    }
}
