<?php

namespace EMS\CoreBundle\ContentTransformer;

class ContentTransformContext
{
    /** @var array $context */
    private $context;

    public function __construct(array $context)
    {
        $this->context = $context;
    }

    public function get(): array
    {
        return $this->context;
    }
}
