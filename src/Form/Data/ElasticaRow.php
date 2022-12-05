<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;

final class ElasticaRow implements TableRowInterface
{
    public function __construct(private readonly DocumentInterface $document)
    {
    }

    public function getData(): DocumentInterface
    {
        return $this->document;
    }
}
