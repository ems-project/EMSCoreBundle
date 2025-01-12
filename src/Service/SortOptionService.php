<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CoreBundle\Entity\SortOption;

class SortOptionService extends EntityService
{
    #[\Override]
    protected function getRepositoryIdentifier(): string
    {
        return SortOption::class;
    }

    #[\Override]
    protected function getEntityName(): string
    {
        return 'Sort Option';
    }
}
