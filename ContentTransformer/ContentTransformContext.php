<?php

namespace EMS\CoreBundle\ContentTransformer;

class ContentTransformContext
{
    /** @var string */
    private $dataFieldType;

    /** @var mixed */
    private $data;

    /** @var mixed */
    private $transformedData;

    private function __construct()
    {
    }

    public static function fromDataFieldType(string $dataFieldType, $data): self
    {
        $context = new self();
        $context->dataFieldType = $dataFieldType;
        $context->data = $data;
        return $context;
    }

    public function hasChanges(): bool
    {
        return $this->data !== $this->transformedData;
    }

    public function getDataFieldType(): string
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
