<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\DataTable;

enum DataTableFormat: string
{
    case TABLE = 'table';
    case CSV = 'csv';
    case EXCEL = 'excel';
}
