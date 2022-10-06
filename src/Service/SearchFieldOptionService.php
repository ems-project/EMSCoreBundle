<?php

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\SearchFieldOption;

class SearchFieldOptionService extends EntityService
{
    protected function getRepositoryIdentifier(): string
    {
        return SearchFieldOption::class;
    }

    protected function getEntityName(): string
    {
        return 'Search Field Option';
    }
}
