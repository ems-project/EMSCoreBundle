<?php

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\SortOption;

class SortOptionService extends EntityService
{
    protected function getRepositoryIdentifier(): string
    {
        return SortOption::class;
    }

    protected function getEntityName(): string
    {
        return 'Sort Option';
    }
}
