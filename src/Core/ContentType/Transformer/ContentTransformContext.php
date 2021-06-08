<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\ContentType\Transformer;

class ContentTransformContext
{
    private string $dataFieldType;
    /** @var mixed */
    private $data;
    /** @var mixed */
    private $transformedData;

    private function __construct()
    {
    }

    /**
     * @param mixed $data
     */
    public static function fromDataFieldType(string $dataFieldType, $data): self
    {
        $context = new self();
        $context->dataFieldType = $dataFieldType;
        $context->data = $data;

        return $context;
    }

    public function getDataFieldType(): string
    {
        return $this->dataFieldType;
    }

    /**
     * @return mixed
     */
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
