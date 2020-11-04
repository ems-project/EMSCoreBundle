<?php

namespace EMS\CoreBundle\Service;

class SortOptionService extends EntityService
{
    
    protected function getRepositoryIdentifier()
    {
        return 'EMSCoreBundle:SortOption';
    }
    
    protected function getEntityName()
    {
        return 'Sort Option';
    }
}
