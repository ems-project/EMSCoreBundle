<?php

namespace EMS\CoreBundle\ContentTransformer;

use EMS\CoreBundle\Form\DataField\DataFieldType;

class ContentTransformContext
{
    /** @var DataFieldType */
    private $dataFieldType;

    /** @var mixed */
    private $data;

    /** @var mixed */
    private $transformedData;

    private function __construct()
    {
    }

    static public function fromDataFieldType(DataFieldType $dataFieldType, $data): self
    {
        $context = new self();
        $context->dataFieldType = $dataFieldType;
        $context->data = $data;
        return $context;
    }

    public function getDataFieldType(): DataFieldType
    {
        return $this->dataFieldType;
    }

    public function getData()
    {
        return $this->data;
    }

    /**
     * @return mixed
     */
    public function getTransformedData()
    {
        return $this->transformedData;
    }

    /**
     * @param mixed $transformedData
     */
    public function setTransformedData($transformedData): void
    {
        $this->transformedData = $transformedData;
    }
}
