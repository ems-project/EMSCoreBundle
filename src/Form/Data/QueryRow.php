<?php

namespace EMS\CoreBundle\Form\Data;

class QueryRow
{
    /**
     * @param mixed[] $data
     */
    public function __construct(private readonly array $data)
    {
    }

    /**
     * @return mixed[]
     */
    public function getData(): array
    {
        return $this->data;
    }
}
