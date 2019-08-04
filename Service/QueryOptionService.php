<?php

namespace EMS\CoreBundle\Service;

class QueryOptionService extends EntityService
{
    
    protected function getRepositoryIdentifier()
    {
        return 'EMSCoreBundle:QueryOption';
    }
    
    protected function getEntityName()
    {
        return 'Query Option';
    }
}
