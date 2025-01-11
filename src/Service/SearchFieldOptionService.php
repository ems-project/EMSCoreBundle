<?php

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\SearchFieldOption;

class SearchFieldOptionService extends EntityService
{
    #[\Override]
    protected function getRepositoryIdentifier(): string
    {
        return SearchFieldOption::class;
    }

    #[\Override]
    protected function getEntityName(): string
    {
        return 'Search Field Option';
    }
}
