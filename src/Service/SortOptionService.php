<?php

namespace EMS\CoreBundle\Service;

class SortOptionService extends EntityService
{
    protected function getRepositoryIdentifier(): string
    {
        return 'EMSCoreBundle:SortOption';
    }

    protected function getEntityName(): string
    {
        return 'Sort Option';
    }
}
