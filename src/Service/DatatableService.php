<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

use EMS\CommonBundle\Service\ElasticaService;
use EMS\CoreBundle\Form\Data\ElasticaTable;

final class DatatableService
{
    private ElasticaService $elasticaService;

    public function __construct(ElasticaService $elasticaService)
    {
        $this->elasticaService = $elasticaService;
    }

    /**
     * @param string[]             $environmentNames
     * @param string[]             $contentTypeNames
     * @param array<string, mixed> $jsonConfig
     */
    public function generateDatatable(array $environmentNames, array $contentTypeNames, array $jsonConfig): ElasticaTable
    {
        return ElasticaTable::fromConfig($this->elasticaService, $environmentNames, $contentTypeNames, $jsonConfig);
    }
}
