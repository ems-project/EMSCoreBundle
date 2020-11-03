<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

class AggregateOptionService extends EntityService
{
    protected function getRepositoryIdentifier()
    {
        return 'EMSCoreBundle:AggregateOption';
    }

    protected function getEntityName()
    {
        return 'Aggregate Option';
    }
}
