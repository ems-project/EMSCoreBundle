<?php

namespace EMS\CoreBundle\Service;

class SearchFieldOptionService extends EntityService
{
    protected function getRepositoryIdentifier(): string
    {
        return 'EMSCoreBundle:SearchFieldOption';
    }

    protected function getEntityName(): string
    {
        return 'Search Field Option';
    }
}
