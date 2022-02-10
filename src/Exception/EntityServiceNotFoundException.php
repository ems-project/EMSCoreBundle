<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Exception;

class EntityServiceNotFoundException extends ElasticmsException
{
    private string $entityName;

    public function __construct(string $entityName)
    {
        $this->entityName = $entityName;
        parent::__construct(\sprintf('Entity service %s not found', $entityName));
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }
}
