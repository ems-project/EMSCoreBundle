<?php

namespace EMS\CoreBundle\Form\Data;

class QueryRow implements TableRowInterface
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
    #[\Override]
    public function getData(): array
    {
        return $this->data;
    }
}
