<?php

namespace EMS\CoreBundle\Core\Table;

/**
 * Bridge class for jQuery DataTables
 *
 * https://datatables.net/
 */
class DataTable extends AbstractTable
{
    public function getTemplate(): string
    {
        return $this->options['template'];
    }
}