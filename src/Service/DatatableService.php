<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Service;

final class DatatableService
{
    public function __construct()
    {
    }

    /**
     * @param string[]             $environmentNames
     * @param string[]             $contentTypeNames
     * @param array<string, mixed> $jsonConfig
     */
    public function generateDatatable(array $environmentNames, array $contentTypeNames, array $jsonConfig): string
    {
        return '<span>foobar</span>';
    }
}
