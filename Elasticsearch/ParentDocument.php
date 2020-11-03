<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Elasticsearch;

use EMS\CommonBundle\Elasticsearch\DocumentInterface;

interface ParentDocument extends DocumentInterface
{
    /**
     * @return DocumentInterface[]
     */
    public function getChildren(): array;
}
