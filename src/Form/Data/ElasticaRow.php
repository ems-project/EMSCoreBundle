<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use EMS\CommonBundle\Elasticsearch\Document\DocumentInterface;

final class ElasticaRow implements TableRowInterface
{
    private DocumentInterface $document;

    public function __construct(DocumentInterface $document)
    {
        $this->document = $document;
    }

    public function getData(): DocumentInterface
    {
        return $this->document;
    }
}
