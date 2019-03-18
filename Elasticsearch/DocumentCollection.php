<?php

namespace EMS\CoreBundle\Elasticsearch;

use EMS\CommonBundle\Elasticsearch\DocumentInterface;

class DocumentCollection
{
    private $collection = [];

    public function collect(DocumentInterface $object)
    {
        if (!$object instanceof ParentDocument) {
            return;
        }

        foreach ($object->getChildren() as $child) {
            if (isset($this->collection[$child->getType()][$child->getEmsId()])) {
                continue;
            }

            $this->collection[$child->getType()][$child->getEmsId()] = $child;
        }
    }

    public function loop(): \Generator
    {
        foreach ($this->collection as $type => $children) {
            yield [$type, $children];
        }
    }
}