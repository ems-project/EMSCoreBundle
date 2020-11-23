<?php

namespace EMS\CoreBundle\Service;

class SearchFieldOptionService extends EntityService
{
    protected function getRepositoryIdentifier()
    {
        return 'EMSCoreBundle:SearchFieldOption';
    }

    protected function getEntityName()
    {
        return 'Search Field Option';
    }
}
