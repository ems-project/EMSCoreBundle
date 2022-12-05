<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Exception;

class EntityServiceNotFoundException extends ElasticmsException
{
    public function __construct(private readonly string $entityName)
    {
        parent::__construct(\sprintf('Entity service %s not found', $entityName));
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }
}
