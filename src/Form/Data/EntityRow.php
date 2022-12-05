<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Form\Data;

use EMS\CommonBundle\Entity\EntityInterface;

final class EntityRow implements TableRowInterface
{
    public function __construct(private readonly EntityInterface $entity)
    {
    }

    public function getData(): EntityInterface
    {
        return $this->entity;
    }
}
