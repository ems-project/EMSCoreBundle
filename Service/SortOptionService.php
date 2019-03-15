<?php

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\SortOption;

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
